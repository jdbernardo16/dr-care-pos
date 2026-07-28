<?php

namespace App\Console\Commands;

use App\Models\Media;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductGallery;
use App\Models\Role;
use App\Services\MediaService;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class FetchProductImagesCommand extends Command
{
    protected $signature = 'ns:fetch-product-images
        {--category= : Only process products in a specific category name}
        {--limit= : Max products to process}
        {--force : Re-process even if thumbnail_id is already set}
        {--quality=60 : WebP quality (0-100, lower = smaller file)}
        {--max-width=400 : Max image width in pixels}
        {--delay=300 : Delay in ms between requests to avoid rate limiting}
        {--no-skip : With --force, reprocess even products that already have real images}';

    protected $description = 'Search the web for real product images, optimize to WebP, and assign';

    private Client $http;
    private int $requestCount = 0;

    public function handle(MediaService $mediaService): int
    {
        $quality = min(100, max(1, (int) $this->option('quality')));
        $maxWidth = (int) $this->option('max-width');
        $force = $this->option('force');
        $categoryName = $this->option('category');
        $limit = $this->option('limit');
        $delay = (int) $this->option('delay');

        $query = Product::query();
        if ($categoryName) {
            $categoryIds = ProductCategory::where('name', $categoryName)->pluck('id');
            if ($categoryIds->isEmpty()) {
                $this->error("Category not found: {$categoryName}");
                return Command::FAILURE;
            }
            $query->whereIn('category_id', $categoryIds);
        }
        if (!$force) {
            $query->whereNull('thumbnail_id');
        } elseif (!$this->option('no-skip')) {
            $processedIds = ProductGallery::where('featured', true)
                ->where('url', 'not like', '%placeholder%')
                ->pluck('product_id');
            $query->whereNotIn('id', $processedIds);
        }
        if ($limit) {
            $query->limit((int) $limit);
        }

        $products = $query->orderBy('id')->get();
        $total = $products->count();

        if ($total === 0) {
            $this->info('No products to process.');
            return Command::SUCCESS;
        }

        $this->info("Processing {$total} products...");

        $adminUser = Role::namespace('admin')->users->first();
        if ($adminUser) {
            Auth::login($adminUser);
        }

        $this->http = new Client([
            'timeout' => 15,
            'http_errors' => false,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            ],
        ]);

        $found = 0;
        $skipped = 0;
        $failed = 0;

        $this->withProgressBar($products, function (Product $product) use ($mediaService, $quality, $maxWidth, $force, $delay, &$found, &$skipped, &$failed) {
            $existingGallery = ProductGallery::where('product_id', $product->id)
                ->where('featured', true)
                ->first();

            if (!$force && $existingGallery && $existingGallery->url) {
                $skipped++;
                return;
            }

            if ($this->requestCount > 0 && $delay > 0) {
                usleep($delay * 1000);
            }

            $imageUrl = $this->searchImageDdg($product->name . ' product Philippines');
            $this->requestCount++;

            if (!$imageUrl) {
                $failed++;
                return;
            }

            $sourcePath = $this->downloadImage($imageUrl);
            if (!$sourcePath) {
                $failed++;
                return;
            }

            $webpPath = $this->convertToWebp($sourcePath, $maxWidth, $quality);
            @unlink($sourcePath);

            if (!$webpPath) {
                $failed++;
                return;
            }

            $mediaId = $this->uploadImage($mediaService, $webpPath, $product);
            @unlink($webpPath);

            if (!$mediaId) {
                $failed++;
                return;
            }

            $this->assignImage($product, $mediaId);
            $found++;
        });

        $this->newLine();
        $this->newLine();
        $this->table(
            ['Status', 'Count'],
            [
                ['Found & assigned', $found],
                ['Skipped (already have)', $skipped],
                ['No image found', $failed],
                ['Total', $found + $skipped + $failed],
            ]
        );

        return Command::SUCCESS;
    }

    private function searchImageDdg(string $query): ?string
    {
        try {
            $searchUrl = 'https://duckduckgo.com/?q=' . urlencode($query) . '&iax=images&ia=images';

            $response = $this->http->get($searchUrl, [
                'headers' => [
                    'Accept' => 'text/html,application/xhtml+xml',
                ],
            ]);

            if ($response->getStatusCode() !== 200) {
                return null;
            }

            $html = (string) $response->getBody();
            if (!preg_match('/vqd=([^"&]+)/', $html, $match)) {
                return null;
            }
            $vqd = $match[1];

            $apiUrl = 'https://duckduckgo.com/i.js?q=' . urlencode($query) . '&vqd=' . urlencode($vqd) . '&o=json&p=1&v=1&f=,,,&l=us-en&ct=US';

            $apiResponse = $this->http->get($apiUrl, [
                'headers' => [
                    'Referer' => 'https://duckduckgo.com/',
                    'Accept' => 'application/json',
                ],
            ]);

            if ($apiResponse->getStatusCode() !== 200) {
                return null;
            }

            $data = json_decode((string) $apiResponse->getBody(), true);
            if (!$data || empty($data['results'])) {
                return null;
            }

            foreach ($data['results'] as $result) {
                if (!empty($result['image']) && $this->isValidImageUrl($result['image'])) {
                    return $result['image'];
                }
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function isValidImageUrl(string $url): bool
    {
        if (empty($url)) return false;
        $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        return in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
    }

    private function downloadImage(string $url): ?string
    {
        try {
            $tempPath = tempnam(sys_get_temp_dir(), 'prod_img_') . '.tmp';
            $response = $this->http->get($url, ['sink' => $tempPath]);

            if ($response->getStatusCode() !== 200 || !file_exists($tempPath) || filesize($tempPath) < 100) {
                @unlink($tempPath);
                return null;
            }

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $tempPath);
            finfo_close($finfo);

            if (!str_starts_with($mime, 'image/')) {
                @unlink($tempPath);
                return null;
            }

            return $tempPath;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function convertToWebp(string $sourcePath, int $maxWidth, int $quality): ?string
    {
        try {
            $info = @getimagesize($sourcePath);
            if (!$info) {
                return null;
            }

            [$srcW, $srcH] = $info;
            $mime = $info['mime'];

            $srcImg = match ($mime) {
                'image/jpeg', 'image/jpg' => @imagecreatefromjpeg($sourcePath),
                'image/png' => @imagecreatefrompng($sourcePath),
                'image/webp' => @imagecreatefromwebp($sourcePath),
                'image/gif' => @imagecreatefromgif($sourcePath),
                default => null,
            };

            if (!$srcImg) {
                return null;
            }

            if ($srcW > $maxWidth) {
                $dstW = $maxWidth;
                $dstH = (int) round($srcH * ($maxWidth / $srcW));
                $dstImg = imagecreatetruecolor($dstW, $dstH);
                imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);
                imagedestroy($srcImg);
                $srcImg = $dstImg;
            }

            $webpPath = tempnam(sys_get_temp_dir(), 'prod_webp_') . '.webp';
            imagewebp($srcImg, $webpPath, $quality);
            imagedestroy($srcImg);

            if (!file_exists($webpPath) || filesize($webpPath) < 100) {
                @unlink($webpPath);
                return null;
            }

            return $webpPath;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function uploadImage(MediaService $mediaService, string $webpPath, Product $product): ?int
    {
        try {
            $uploadedFile = new UploadedFile(
                $webpPath,
                $this->slugify($product->name) . '.webp',
                'image/webp',
                null,
                true
            );

            $media = $mediaService->upload($uploadedFile);

            if ($media && isset($media->id)) {
                return $media->id;
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function assignImage(Product $product, int $mediaId): void
    {
        $media = Media::find($mediaId);
        if (!$media) {
            return;
        }

        $product->thumbnail_id = $mediaId;
        $product->save();

        $url = Storage::disk('public')->url($media->slug . '.' . $media->extension);

        ProductGallery::updateOrCreate(
            ['product_id' => $product->id, 'featured' => true],
            [
                'media_id' => $mediaId,
                'url' => $url,
                'featured' => true,
                'order' => 0,
                'author_id' => $product->author_id ?? 1,
                'name' => $product->name . ' Image',
            ]
        );
    }

    private function slugify(string $text): string
    {
        $slug = preg_replace('/[^a-z0-9]+/i', '-', $text);
        return trim($slug, '-');
    }
}
