<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class RestoreWithImagesCommand extends Command
{
    protected $signature = 'pos:restore-with-images {snapshot? : The snapshot name to restore (leave empty to list available)}';

    protected $description = 'Restore a DB snapshot but keep current product images and media';

    const BACKUP_DISK = 'snapshots';

    protected array $mediaTables = [
        'nexopos_medias',
        'nexopos_products_galleries',
    ];

    public function handle()
    {
        $snapshotName = $this->argument('snapshot');

        if (!$snapshotName) {
            $this->call('snapshot:list');
            $snapshotName = $this->ask('Enter the snapshot name to restore');
            if (!$snapshotName) {
                return 1;
            }
        }

        $this->newLine();
        $this->warn("This will restore '{$snapshotName}' and merge in current product images.");
        $this->warn('A full backup of the current state will be taken first as a safety net.');
        $this->newLine();

        if (!$this->confirm('Continue?')) {
            $this->info('Cancelled.');
            return 0;
        }

        $this->newLine();
        $this->info('Step 1/5: Backing up current state (safety net)...');
        $this->call('backup:full');

        $this->newLine();
        $this->info('Step 2/5: Exporting current media tables & thumbnail references...');
        $backupDisk = Storage::disk(self::BACKUP_DISK);
        $date = Carbon::now()->format('Y-m-d-His');
        $mediaSqlFile = "media-export-{$date}.sql";
        $mediaSqlPath = $backupDisk->path($mediaSqlFile);

        $this->exportMediaTables($mediaSqlPath);
        $this->info("Exported to {$mediaSqlFile}");

        $this->newLine();
        $this->info('Step 3/5: Restoring snapshot...');
        Artisan::call('snapshot:load', ['name' => $snapshotName, '--force' => true]);
        $this->info(Artisan::output());

        $this->newLine();
        $this->info('Step 4/5: Importing media tables into restored snapshot...');
        $this->importSqlFile($mediaSqlPath);

        $this->newLine();
        $this->info('Step 5/5: Restoring image files...');
        $this->extractLatestImages($backupDisk);

        $this->call('storage:link');

        $this->newLine();
        $this->info('Complete. The snapshot has been restored with current product images.');
        $this->newLine();
        $this->warn('What was preserved (from current state):');
        $this->warn('  - Product images (files + media records + gallery links + thumbnail refs)');
        $this->warn('What was restored (from snapshot):');
        $this->warn('  - Everything else (orders, stock, customers, settings, etc.)');
        $this->newLine();
        $this->warn('If you need to undo, restore the safety backup:');
        $this->warn('  php artisan snapshot:list');
        $this->warn('  php artisan snapshot:load backup-YYYYMMDD-HHmmss');

        return 0;
    }

    protected function exportMediaTables(string $outputPath): void
    {
        $lines = [];
        $lines[] = '-- Media & gallery export for patching images into a restored snapshot';
        $lines[] = '-- Generated: ' . now();
        $lines[] = '';

        foreach ($this->mediaTables as $table) {
            if (!Schema::hasTable($table)) {
                $this->warn("Table {$table} does not exist, skipping.");
                continue;
            }

            $rows = DB::table($table)->get();
            if ($rows->isEmpty()) {
                $this->info("Table {$table} is empty, skipping.");
                continue;
            }

            $lines[] = "-- {$table}: {$rows->count()} rows";

            $columns = array_keys(get_object_vars($rows->first()));

            foreach ($rows as $row) {
                $values = [];
                foreach ($columns as $col) {
                    $val = $row->$col ?? null;
                    if ($val === null) {
                        $values[] = 'NULL';
                    } elseif (is_numeric($val)) {
                        $values[] = $val;
                    } else {
                        $values[] = "'" . str_replace("'", "\\'", (string) $val) . "'";
                    }
                }
                $lines[] = 'REPLACE INTO `' . $table . '` (`' . implode('`, `', $columns) . '`) VALUES (' . implode(', ', $values) . ');';
            }
            $lines[] = '';
        }

        $thumbnails = DB::table('nexopos_products')
            ->whereNotNull('thumbnail_id')
            ->select(['id', 'thumbnail_id'])
            ->get();

        if ($thumbnails->isNotEmpty()) {
            $lines[] = '-- Restore thumbnail_id references on products';
            foreach ($thumbnails as $p) {
                $lines[] = "UPDATE `nexopos_products` SET `thumbnail_id` = {$p->thumbnail_id} WHERE `id` = {$p->id};";
            }
            $lines[] = '';
        }

        file_put_contents($outputPath, implode("\n", $lines));
    }

    protected function importSqlFile(string $path): void
    {
        if (!file_exists($path)) {
            $this->error("SQL file not found: {$path}");
            return;
        }

        $contents = file_get_contents($path);
        $statements = explode(';', $contents);

        DB::beginTransaction();
        try {
            foreach ($statements as $statement) {
                $statement = trim($statement);
                if (empty($statement) || str_starts_with($statement, '--')) {
                    continue;
                }
                DB::unprepared($statement . ';');
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Failed to import media tables: ' . $e->getMessage());
            throw $e;
        }
    }

    protected function extractLatestImages($backupDisk): void
    {
        $imagesArchives = collect($backupDisk->files())
            ->filter(fn($f) => str_starts_with($f, 'images-backup-'))
            ->sort()
            ->reverse()
            ->values();

        if ($imagesArchives->isEmpty()) {
            $this->warn('No image archives found. Images may be missing after restore.');
            return;
        }

        $latest = $imagesArchives->first();
        $this->info("Extracting images from: {$latest}...");
        $archivePath = $backupDisk->path($latest);
        $extractTo = storage_path('app/public');

        $command = sprintf(
            'tar -xzf %s -C %s',
            escapeshellarg($archivePath),
            escapeshellarg($extractTo)
        );
        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            $this->error('Failed to extract images archive.');
        }
    }
}
