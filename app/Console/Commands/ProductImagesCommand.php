<?php

namespace App\Console\Commands;

use App\Models\Media;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductGallery;
use App\Models\Role;
use App\Services\MediaService;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductImagesCommand extends Command
{
    protected $signature = 'ns:product-images
        {--generate-only : Only generate SVG placeholders, skip upload}
        {--category= : Process only products in a specific category ID}
        {--limit= : Max products to process}
        {--force : Re-process even if thumbnail_id is already set}';

    protected $description = 'Generate and assign product images';

    private array $categoryColors = [
        'Pain Relief' => ['bg' => [239, 68, 68], 'icon' => "\xF0\x9F\x92\x8A"],
        'Antibiotics' => ['bg' => [139, 92, 246], 'icon' => "\xF0\x9F\xA6\xA0"],
        'Vitamins & Supplements' => ['bg' => [16, 185, 129], 'icon' => "\xF0\x9F\x92\x9A"],
        'Cough & Cold' => ['bg' => [59, 130, 246], 'icon' => "\xF0\x9F\xA4\xA7"],
        'Allergy & Sinus' => ['bg' => [245, 158, 11], 'icon' => "\xF0\x9F\x8C\xBF"],
        'Digestive Health' => ['bg' => [132, 204, 22], 'icon' => "\xF0\x9F\xA5\xBE"],
        'Skin Care' => ['bg' => [236, 72, 153], 'icon' => "\xF0\x9F\xA7\xB4"],
        'Eye & Ear Care' => ['bg' => [6, 182, 212], 'icon' => "\xF0\x9F\x91\x81\xEF\xB8\x8F"],
        'First Aid' => ['bg' => [220, 38, 38], 'icon' => "\xE2\x9B\x91\xEF\xB8\x8F"],
        'Diabetes Care' => ['bg' => [99, 102, 241], 'icon' => "\xF0\x9F\xA9\xB8"],
        'Heart Health' => ['bg' => [244, 63, 94], 'icon' => "\xE2\x9D\xA4\xEF\xB8\x8F"],
        'Baby & Child Care' => ['bg' => [167, 139, 250], 'icon' => "\xF0\x9F\x91\xB6"],
        'Personal Care' => ['bg' => [20, 184, 166], 'icon' => "\xF0\x9F\xA7\xBC"],
        'Medical Devices' => ['bg' => [100, 116, 139], 'icon' => "\xF0\x9F\x8F\xA5"],
        'Medical Devices & Consumables' => ['bg' => [100, 116, 139], 'icon' => "\xF0\x9F\x8F\xA5"],
        'Food & Beverages' => ['bg' => [249, 115, 22], 'icon' => "\xF0\x9F\x8D\xBD\xEF\xB8\x8F"],
        'General' => ['bg' => [107, 114, 128], 'icon' => "\xF0\x9F\x93\xA6"],
        'General (Stationery)' => ['bg' => [107, 114, 128], 'icon' => "\xF0\x9F\x93\x8E"],
        'Default Category' => ['bg' => [156, 163, 175], 'icon' => "\xF0\x9F\x93\x8B"],
    ];

    private array $defaultColor = ['bg' => [107, 114, 128], 'icon' => "\xF0\x9F\x93\xA6"];

    public function handle(MediaService $mediaService): int
    {
        $generateOnly = $this->option('generate-only');
        $categoryId = $this->option('category');
        $limit = $this->option('limit');
        $force = $this->option('force');

        $query = ProductCategory::query();
        if ($categoryId) {
            $query->where('id', $categoryId);
        }
        $categories = $query->get();

        $adminUser = Role::namespace('admin')->users->first();
        if ($adminUser) {
            Auth::login($adminUser);
        }

        $processed = 0;
        $totalProducts = 0;
        $failedCategories = [];

        foreach ($categories as $category) {
            $this->info("Processing category: {$category->name}");

            $config = $this->categoryColors[$category->name] ?? $this->defaultColor;

            $products = Product::where('category_id', $category->id);
            if (!$force) {
                $products->whereNull('thumbnail_id');
            }
            if ($limit) {
                $products->limit((int) $limit);
            }
            $products = $products->get();
            $totalProducts += $products->count();

            if ($products->isEmpty()) {
                $this->line("  No unprocessed products in this category, skipping.");
                continue;
            }

            $categoryImageMediaId = null;

            if (!$generateOnly) {
                $categoryImageMediaId = $this->uploadCategoryPlaceholder($mediaService, $category, $config);
                if (!$categoryImageMediaId) {
                    $this->warn("  Failed to create image for category: {$category->name}");
                    $failedCategories[] = $category->name;
                    continue;
                }
                $this->line("  Uploaded placeholder image (media_id: {$categoryImageMediaId})");
            }

            foreach ($products as $product) {
                if ($generateOnly) {
                    $this->generateProductSvg($product, $category, $config);
                } else {
                    $this->assignProductImage($product, $categoryImageMediaId);
                }
                $processed++;
            }

            if (!$generateOnly) {
                $total = $products->count();
                $this->line("  Assigned {$total} products in category: {$category->name}");
            }
        }

        $this->newLine();
        if ($generateOnly) {
            $this->info("Generated SVG placeholders for {$processed} products across " . $categories->count() . " categories.");
        } else {
            $this->info("Uploaded and assigned images to {$processed}/{$totalProducts} products.");
            if (!empty($failedCategories)) {
                $this->warn("Failed categories: " . implode(', ', $failedCategories));
            }
        }

        return Command::SUCCESS;
    }

    private function uploadCategoryPlaceholder(MediaService $mediaService, ProductCategory $category, array $config): ?int
    {
        $tempPng = tempnam(sys_get_temp_dir(), 'prod_img_') . '.png';
        $tempWebp = tempnam(sys_get_temp_dir(), 'prod_img_') . '.webp';

        try {
            $this->renderPlaceholderImage($tempPng, $category->name, $config['bg'], $config['icon']);

            if (!file_exists($tempPng)) {
                return null;
            }

            if ($this->convertToWebp($tempPng, $tempWebp) && file_exists($tempWebp)) {
                $sourceFile = $tempWebp;
                $mime = 'image/webp';
                $ext = 'webp';
            } else {
                $sourceFile = $tempPng;
                $mime = 'image/png';
                $ext = 'png';
            }

            $uploadedFile = new UploadedFile(
                $sourceFile,
                $this->slugify($category->name) . '-placeholder.' . $ext,
                $mime,
                null,
                true
            );

            $media = $mediaService->upload($uploadedFile);

            if ($media && isset($media->id)) {
                return $media->id;
            }

            return null;
        } finally {
            if (file_exists($tempPng)) unlink($tempPng);
            if (file_exists($tempWebp)) unlink($tempWebp);
        }
    }

    private function slugify(string $text): string
    {
        $slug = preg_replace('/[^a-z0-9]+/i', '-', $text);
        return trim($slug, '-');
    }

    private function renderPlaceholderImage(string $outputPath, string $name, array $rgb, string $icon): void
    {
        $size = 200;
        $img = imagecreatetruecolor($size, $size);

        $bgColor = imagecolorallocate($img, $rgb[0], $rgb[1], $rgb[2]);
        imagefill($img, 0, 0, $bgColor);

        $white = imagecolorallocate($img, 255, 255, 255);

        $initials = $this->getInitials($name);

        $fontSize = 56;
        $fontPath = $this->getFontPath();

        if ($fontPath) {
            $tw = $size / 2;
            $x = $size / 2 - $tw / 2;
            imagettftext($img, $fontSize, 0, (int)$x, 95, $white, $fontPath, $initials);
        } else {
            $textX = $size / 2 - (strlen($initials) * 12);
            imagestring($img, 5, (int)max(0, $textX), 85, $initials, $white);
        }

        imagepng($img, $outputPath, 9);
        imagedestroy($img);
    }

    private function getFontPath(): ?string
    {
        $candidates = [
            '/System/Library/Fonts/Helvetica.ttc',
            '/System/Library/Fonts/Helvetica.ttf',
            '/System/Library/Fonts/Arial.ttf',
            '/System/Library/Fonts/Supplemental/Arial.ttf',
            '/Library/Fonts/Arial.ttf',
        ];

        foreach ($candidates as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }

    private function convertToWebp(string $inputPath, string $outputPath): bool
    {
        $convertBinary = '/opt/homebrew/bin/convert';
        if (!file_exists($convertBinary)) {
            $convertBinary = trim(shell_exec('which convert 2>/dev/null') ?? '');
            if (empty($convertBinary)) return false;
        }

        $cmd = sprintf(
            '%s "%s" -resize 200x200 -quality 85 -define webp:method=6 "%s" 2>/dev/null',
            $convertBinary,
            $inputPath,
            $outputPath
        );

        exec($cmd, $output, $exitCode);
        return $exitCode === 0 && file_exists($outputPath);
    }

    private function getInitials(string $name): string
    {
        $words = preg_split('/[\s\-]+/', $name);
        $initials = '';
        foreach ($words as $w) {
            $chars = preg_split('//u', $w, -1, PREG_SPLIT_NO_EMPTY);
            if (!empty($chars)) {
                $initials .= mb_strtoupper($chars[0]);
            }
        }
        return mb_substr($initials, 0, 3);
    }

    private function assignProductImage(Product $product, int $mediaId): void
    {
        $product->thumbnail_id = $mediaId;

        if (!$product->save()) {
            $this->warn("  Failed to save thumbnail_id for product ID {$product->id}");
            return;
        }

        $existingGallery = ProductGallery::where('product_id', $product->id)
            ->where('featured', true)
            ->first();

        if (!$existingGallery) {
            ProductGallery::create([
                'product_id' => $product->id,
                'media_id' => $mediaId,
                'featured' => true,
                'order' => 0,
                'author_id' => $product->author_id,
                'name' => $product->name . ' Image',
            ]);
        }
    }

    private function generateProductSvg(Product $product, ProductCategory $category, array $config): void
    {
        $outputDir = storage_path('app/public/products/placeholders');
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $filename = 'product-' . $product->id . '-' . $this->slugify($product->name) . '.svg';

        $tempPng = tempnam(sys_get_temp_dir(), 'prod_') . '.png';
        $this->renderPlaceholderImage($tempPng, $product->name, $config['bg'], $config['icon']);

        if (file_exists($tempPng)) {
            copy($tempPng, $outputDir . '/' . str_replace('.svg', '.png', $filename));
            unlink($tempPng);
        }
    }
}
