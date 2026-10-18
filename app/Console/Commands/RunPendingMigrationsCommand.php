<?php

namespace App\Console\Commands;

use App\Services\UpdateService;
use Illuminate\Console\Command;

class RunPendingMigrationsCommand extends Command
{
    protected $signature = 'ns:run-pending-migrations';

    protected $description = 'Run all pending NexoPOS migrations (create, update, core).';

    public function handle( UpdateService $updateService )
    {
        $files = $updateService->getMigrations();

        if ( $files->isEmpty() ) {
            return $this->info( '✅ No pending migrations found.' );
        }

        $this->info( sprintf( 'Found %d pending migration(s):', $files->count() ) );
        $files->each( fn( $f ) => $this->line( "  - {$f}" ) );

        if ( ! $this->confirm( 'Run these migrations?' ) ) {
            return $this->info( 'Aborted.' );
        }

        $this->withProgressBar( $files, function ( $file ) use ( $updateService ) {
            $updateService->executeMigrationFromFileName( $file );
        } );

        $this->newLine();
        $this->info( '✅ All pending migrations completed.' );
    }
}
