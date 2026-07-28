<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

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
        $command = sprintf(
            'tar -czf %s -C %s .',
            escapeshellarg($imagesArchivePath),
            escapeshellarg($imagesDir)
        );
        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            $this->error('Failed to archive images.');
            return 1;
        }

        $this->info("Backup complete: {$snapshotName}");

        $this->cleanOldBackups((int) $this->option('delete-older-than'));

        return 0;
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
