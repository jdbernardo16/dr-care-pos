<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ResetTransactionsCommand extends Command
{
    protected $signature = 'pos:reset-transactions {--force : Skip confirmation prompt}';

    protected $description = 'Delete all order/transaction data while preserving products, images, stock, and settings';

    protected array $orderTables = [
        'nexopos_orders',
        'nexopos_orders_products',
        'nexopos_orders_payments',
        'nexopos_orders_coupons',
        'nexopos_orders_taxes',
        'nexopos_orders_metas',
        'nexopos_orders_addresses',
        'nexopos_orders_instalments',
        'nexopos_orders_settings',
        'nexopos_orders_refunds',
        'nexopos_orders_products_refunds',
        'nexopos_orders_storage',
        'nexopos_orders_count',
    ];

    protected array $historyTables = [
        'nexopos_products_histories',
        'nexopos_products_histories_combined',
        'nexopos_dashboard_days',
        'nexopos_dashboard_weeks',
        'nexopos_dashboard_months',
        'nexopos_registers_history',
    ];

    public function handle()
    {
        if (! $this->option('force')) {
            $confirmed = $this->confirm(
                'This will delete ALL orders, payments, refunds, and sales history. '
                . 'Products, images, stock quantities, customers, and settings will be preserved. '
                . 'Are you sure?'
            );

            if (! $confirmed) {
                $this->info('Cancelled.');
                return 0;
            }
        }

        $this->info('Creating a backup snapshot before reset...');
        $snapshotName = 'pre-reset-backup-' . now()->format('Y-m-d-His');
        $this->call('snapshot:create', ['name' => $snapshotName]);
        $this->info("Backup saved as: {$snapshotName}");

        DB::beginTransaction();
        try {
            foreach ($this->orderTables as $table) {
                if (Schema::hasTable($table)) {
                    $count = DB::table($table)->count();
                    DB::table($table)->delete();
                    $this->info("Cleared {$table}: {$count} rows deleted");
                }
            }

            foreach ($this->historyTables as $table) {
                if (Schema::hasTable($table)) {
                    $count = DB::table($table)->count();
                    DB::table($table)->delete();
                    $this->info("Cleared {$table}: {$count} rows deleted");
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Failed: ' . $e->getMessage());
            return 1;
        }

        $this->newLine();
        $this->info('Done. Transactions cleared.');
        $this->warn('Stock quantities were NOT changed — they remain at current levels.');
        $this->warn('A backup was saved as "' . $snapshotName . '" in case you need to undo this.');

        return 0;
    }
}
