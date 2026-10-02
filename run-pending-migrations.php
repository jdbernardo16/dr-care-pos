<?php
/**
 * Standalone script to run pending NexoPOS migrations.
 *
 * Usage (from project root):
 *   php run-pending-migrations.php
 *
 * This bootstraps Laravel without needing tinker.
 * Ideal for shared hosting (Hostinger, etc.).
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make( Illuminate\Contracts\Console\Kernel::class );
$kernel->bootstrap();

// Get the UpdateService
$updateService = $app->make( App\Services\UpdateService::class );

echo "=== NexoPOS Pending Migrations ===\n\n";

// 1. Run standard Laravel migrations first
echo "[1/3] Running standard Laravel migrations...\n";
Illuminate\Support\Facades\Artisan::call( 'migrate', [ '--force' => true ] );
echo Illuminate\Support\Facades\Artisan::output();
echo "\n";

// 2. Run custom NexoPOS migrations
echo "[2/3] Checking for pending NexoPOS migrations...\n";
$files = $updateService->getMigrations();

if ( $files->isEmpty() ) {
    echo "  ✅ No pending migrations found.\n";
} else {
    echo "  Found " . $files->count() . " pending migration(s):\n";
    foreach ( $files as $file ) {
        echo "    → {$file}\n";
    }
    echo "\n  Executing...\n";

    foreach ( $files as $file ) {
        try {
            $updateService->executeMigrationFromFileName( $file );
            echo "    ✅ {$file}\n";
        } catch ( \Throwable $e ) {
            echo "    ❌ {$file}: " . $e->getMessage() . "\n";
        }
    }
    echo "\n  ✅ All NexoPOS migrations completed.\n";
}

// 3. Clear cache
echo "\n[3/3] Clearing cache...\n";
Illuminate\Support\Facades\Artisan::call( 'cache:clear' );

echo "\n=== Done! ===\n";
