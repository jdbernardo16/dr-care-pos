<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Phar;
use PharData;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class FullBackupCommand extends Command
{
    protected $signature = 'backup:full {--delete-older-than=30 : Delete backups older than this many days}';

    protected $description = 'Create a full backup including database snapshot and uploaded images';

    const BACKUP_DISK = 'snapshots';

    public function handle()
    {
        $date = Carbon::now()->format('Y-m-d-His');
        $snapshotName = "backup-{$date}";

        $this->info("Creating database snapshot: {$snapshotName}...");
        Artisan::call('snapshot:create', ['name' => $snapshotName]);
        $this->info(Artisan::output());

        $backupDisk = Storage::disk(self::BACKUP_DISK);
        $imagesDir = storage_path('app/public');

        if (!is_dir($imagesDir)) {
            $this->warn('No images directory found at ' . $imagesDir);
            return 0;
        }

        $imagesArchive = "images-backup-{$date}.tar.gz";
        $imagesArchivePath = $backupDisk->path($imagesArchive);

        $this->info("Archiving images to: {$imagesArchivePath}...");

        $this->createTarGz($imagesDir, $imagesArchivePath);

        $this->info("Backup complete: {$snapshotName}");

        $this->cleanOldBackups((int) $this->option('delete-older-than'));

        return 0;
    }

    protected function createTarGz(string $sourceDir, string $outputPath): void
    {
        $tarPath = preg_replace('/\.gz$/', '', $outputPath);
        $phar = new PharData($tarPath);

        $directory = new RecursiveDirectoryIterator(
            $sourceDir,
            RecursiveDirectoryIterator::SKIP_DOTS
        );

        $filtered = new RecursiveCallbackFilterIterator($directory, function ($current) {
            return !$current->isLink();
        });

        $iterator = new RecursiveIteratorIterator($filtered, RecursiveIteratorIterator::LEAVES_ONLY);

        $phar->buildFromIterator($iterator, $sourceDir);

        $phar->compress(Phar::GZ);

        if (file_exists($tarPath)) {
            unlink($tarPath);
        }
    }

    protected function extractTarGz(string $archivePath, string $extractTo): void
    {
        $phar = new PharData($archivePath);
        $phar->extractTo($extractTo, null, true);
    }

    protected function cleanOldBackups(int $days): void
    {
        if ($days <= 0) {
            return;
        }

        $backupDisk = Storage::disk(self::BACKUP_DISK);
        $cutoff = Carbon::now()->subDays($days);

        foreach ($backupDisk->files() as $file) {
            $lastModified = Carbon::createFromTimestamp($backupDisk->lastModified($file));
            if ($lastModified->lt($cutoff)) {
                $backupDisk->delete($file);
                $this->info("Deleted old backup: {$file}");
            }
        }
    }
}
