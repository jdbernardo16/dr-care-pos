<?php

namespace Database\Seeders;

use App\Models\Procurement;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductUnitQuantity;
use App\Models\Provider;
use App\Models\Role;
use App\Models\Unit;
use App\Models\UnitGroup;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RealInventorySeeder extends Seeder
{
    private array $unitGroupMap = [];

    private array $unitMap = [];

    private array $categoryMap = [];

    private ?int $authorId = null;

    private ?int $providerId = null;

    public function run()
    {
        $this->authorId = Role::namespace('admin')->users->first()->id ?? 1;

        echo "[1/7] Clearing all business data...\n";
        $this->truncateBusinessTables();

        echo "[2/7] Creating base reference data...\n";
        $this->createBaseData();

        echo "[3/7] Creating pharmacy categories...\n";
        $this->createCategories();

        echo "[4/7] Creating unit groups and units...\n";
        $this->createUnits();

        echo "[5/7] Creating default provider...\n";
        $this->createProvider();

        echo "[6/7] Importing {$this->countCsvRows()} products from CSV...\n";
        $procurement = $this->createProcurement();
        $this->importProducts($procurement);

        echo "[7/7] Done!\n";
    }

    private function truncateBusinessTables(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        $tables = [
            'nexopos_products_histories',
            'nexopos_products_histories_combined',
            'nexopos_products_unit_quantities',
            'nexopos_products_galleries',
            'nexopos_products_taxes',
            'nexopos_products_subitems',
            'nexopos_products_metas',
            'nexopos_procurements_products',
            'nexopos_procurements',
            'nexopos_orders_products',
            'nexopos_orders_products_refunds',
            'nexopos_orders_payments',
            'nexopos_orders_taxes',
            'nexopos_orders_coupons',
            'nexopos_orders_instalments',
            'nexopos_orders_refunds',
            'nexopos_orders_addresses',
            'nexopos_orders_metas',
            'nexopos_orders_storage',
            'nexopos_orders_settings',
            'nexopos_orders_count',
            'nexopos_orders',
            'nexopos_products',
            'nexopos_products_categories',
            'nexopos_units',
            'nexopos_units_groups',
            'nexopos_providers',
            'nexopos_customers_addresses',
            'nexopos_customers_account_history',
            'nexopos_customers_rewards',
            'nexopos_customers_coupons',
            'nexopos_customers_groups',
            'nexopos_rewards_system',
            'nexopos_rewards_system_rules',
            'nexopos_coupons',
            'nexopos_coupons_categories',
            'nexopos_coupons_customers',
            'nexopos_coupons_customers_groups',
            'nexopos_coupons_products',
            'nexopos_taxes',
            'nexopos_taxes_groups',
            'nexopos_transactions',
            'nexopos_transactions_accounts',
            'nexopos_transactions_histories',
            'nexopos_transactions_balance_days',
            'nexopos_transactions_balance_months',
            'nexopos_dashboard_days',
            'nexopos_dashboard_months',
            'nexopos_dashboard_weeks',
            'nexopos_registers',
            'nexopos_registers_history',
            'nexopos_payments_types',
            'nexopos_medias',
            'nexopos_notifications',
            'nexopos_holidays',
            'nexopos_attendance',
            'nexopos_overtime_requests',
            'nexopos_payroll_runs',
            'nexopos_payroll_run_items',
            'nexopos_scale_ranges',
            'nexopos_transactions_actions_rules',
        ];

        foreach ($tables as $table) {
            try {
                DB::table($table)->truncate();
            } catch (\Illuminate\Database\QueryException $e) {
                // Fallback: DELETE + reset auto-increment
                try {
                    DB::statement("DELETE FROM {$table}");
                    DB::statement("ALTER TABLE {$table} AUTO_INCREMENT = 1");
                } catch (\Exception $e2) {
                    // Table might not exist, silently skip
                }
            }
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    private function createBaseData(): void
    {
        ProductCategory::create([
            'name' => __('Default Category'),
            'author_id' => $this->authorId,
        ]);

        $unitGroup = UnitGroup::create([
            'name' => __('Countable'),
            'author_id' => $this->authorId,
        ]);

        Unit::create([
            'name' => __('Piece'),
            'value' => 1,
            'identifier' => 'piece',
            'base_unit' => true,
            'group_id' => $unitGroup->id,
            'author_id' => $this->authorId,
        ]);
    }

    private function createCategories(): void
    {
        $categories = [
            ['name' => 'Personal Care', 'description' => 'Hygiene, cosmetics, deodorants, soaps, and personal wellness items.'],
            ['name' => 'Vitamins & Supplements', 'description' => 'Essential vitamins, minerals, and dietary supplements.'],
            ['name' => 'Food & Beverages', 'description' => 'Food items, drinks, snacks, and beverages.'],
            ['name' => 'Heart Health', 'description' => 'Medications for blood pressure, cholesterol, and cardiovascular health.'],
            ['name' => 'Cough & Cold', 'description' => 'Remedies for cough, colds, flu, and respiratory symptoms.'],
            ['name' => 'Pain Relief', 'description' => 'Medicines for pain management including headaches, muscle pain, and fever.'],
            ['name' => 'Skin Care', 'description' => 'Topical treatments, ointments, and creams for skin conditions.'],
            ['name' => 'Digestive Health', 'description' => 'Medicines for stomach issues, acid reflux, and digestive support.'],
            ['name' => 'Baby & Child Care', 'description' => 'Pediatric medicines, vitamins, diapers, and care products for children.'],
            ['name' => 'Antibiotics', 'description' => 'Prescription and over-the-counter antibiotics for bacterial infections.'],
            ['name' => 'First Aid', 'description' => 'Bandages, antiseptics, gauze, and emergency medical supplies.'],
            ['name' => 'Allergy & Sinus', 'description' => 'Antihistamines, decongestants, and corticosteroids for allergies.'],
            ['name' => 'Medical Devices & Consumables', 'description' => 'Syringes, BP monitors, thermometers, and medical equipment.'],
            ['name' => 'Diabetes Care', 'description' => 'Blood sugar monitors, test strips, diabetic supplies, and medications.'],
            ['name' => 'General (Stationery)', 'description' => 'School and office supplies.'],
            ['name' => 'Eye & Ear Care', 'description' => 'Drops and treatments for eye and ear conditions.'],
            ['name' => 'General', 'description' => 'Miscellaneous items.'],
        ];

        foreach ($categories as $cat) {
            $created = ProductCategory::create([
                'name' => $cat['name'],
                'description' => $cat['description'],
                'author_id' => $this->authorId,
                'uuid' => Str::uuid(),
                'displays_on_pos' => true,
                'parent_id' => 0,
                'position' => 0,
            ]);
            $this->categoryMap[$cat['name']] = $created->id;
        }
    }

    private function createUnits(): void
    {
        // Pieces group
        $piecesGroup = UnitGroup::create([
            'name' => 'Pieces',
            'description' => 'Individual countable units like tablets, capsules, and sachets.',
            'author_id' => $this->authorId,
            'uuid' => Str::uuid(),
        ]);
        $pieceUnit = Unit::create([
            'name' => 'Piece', 'value' => 1,
            'identifier' => 'pharm-piece-' . Str::random(5),
            'base_unit' => true, 'group_id' => $piecesGroup->id,
            'author_id' => $this->authorId, 'uuid' => Str::uuid(),
        ]);
        Unit::create([
            'name' => 'Box', 'value' => 10,
            'identifier' => 'pharm-box-' . Str::random(5),
            'base_unit' => false, 'group_id' => $piecesGroup->id,
            'author_id' => $this->authorId, 'uuid' => Str::uuid(),
        ]);
        Unit::create([
            'name' => 'Strip', 'value' => 5,
            'identifier' => 'pharm-strip-' . Str::random(5),
            'base_unit' => false, 'group_id' => $piecesGroup->id,
            'author_id' => $this->authorId, 'uuid' => Str::uuid(),
        ]);

        // Liquid group
        $liquidGroup = UnitGroup::create([
            'name' => 'Liquid',
            'description' => 'Liquid medicine measurements like bottles and milliliters.',
            'author_id' => $this->authorId,
            'uuid' => Str::uuid(),
        ]);
        $bottleUnit = Unit::create([
            'name' => 'Bottle', 'value' => 1,
            'identifier' => 'pharm-bottle-' . Str::random(5),
            'base_unit' => true, 'group_id' => $liquidGroup->id,
            'author_id' => $this->authorId, 'uuid' => Str::uuid(),
        ]);
        Unit::create([
            'name' => 'Pack (6 Bottles)', 'value' => 6,
            'identifier' => 'pharm-pack-' . Str::random(5),
            'base_unit' => false, 'group_id' => $liquidGroup->id,
            'author_id' => $this->authorId, 'uuid' => Str::uuid(),
        ]);

        // Topical group
        $topicalGroup = UnitGroup::create([
            'name' => 'Topical',
            'description' => 'Topical application units like tubes and jars.',
            'author_id' => $this->authorId,
            'uuid' => Str::uuid(),
        ]);
        $tubeUnit = Unit::create([
            'name' => 'Tube', 'value' => 1,
            'identifier' => 'pharm-tube-' . Str::random(5),
            'base_unit' => true, 'group_id' => $topicalGroup->id,
            'author_id' => $this->authorId, 'uuid' => Str::uuid(),
        ]);
        Unit::create([
            'name' => 'Jar', 'value' => 1,
            'identifier' => 'pharm-jar-' . Str::random(5),
            'base_unit' => false, 'group_id' => $topicalGroup->id,
            'author_id' => $this->authorId, 'uuid' => Str::uuid(),
        ]);

        // Roll group
        $rollGroup = UnitGroup::create([
            'name' => 'Roll',
            'description' => 'Rolled items like tapes and bandages.',
            'author_id' => $this->authorId,
            'uuid' => Str::uuid(),
        ]);
        $rollUnit = Unit::create([
            'name' => 'Roll', 'value' => 1,
            'identifier' => 'pharm-roll-' . Str::random(5),
            'base_unit' => true, 'group_id' => $rollGroup->id,
            'author_id' => $this->authorId, 'uuid' => Str::uuid(),
        ]);

        $this->unitGroupMap = [
            'Pieces' => $piecesGroup->id,
            'Liquid' => $liquidGroup->id,
            'Topical' => $topicalGroup->id,
            'Roll' => $rollGroup->id,
        ];

        $this->unitMap = [
            'Pieces' => $pieceUnit->id,
            'Liquid' => $bottleUnit->id,
            'Topical' => $tubeUnit->id,
            'Roll' => $rollUnit->id,
        ];
    }

    private function createProvider(): void
    {
        $provider = Provider::create([
            'first_name' => __('Dr. Care Main Supplier'),
            'author_id' => $this->authorId,
        ]);
        $this->providerId = $provider->id;
    }

    private function createProcurement(): object
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('nexopos_procurements')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $id = DB::table('nexopos_procurements')->insertGetId([
            'name' => 'Initial Inventory Import',
            'provider_id' => $this->providerId,
            'value' => 0,
            'cost' => 0,
            'tax_value' => 0,
            'invoice_reference' => 'IMPORT-' . date('Ymd'),
            'automatic_approval' => true,
            'delivery_time' => now(),
            'invoice_date' => now(),
            'payment_status' => 'paid',
            'delivery_status' => 'stocked',
            'total_items' => 0,
            'author_id' => $this->authorId,
            'uuid' => Str::uuid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Procurement::find($id);
    }

    private function importProducts(Procurement $procurement): void
    {
        $csvPath = base_path('inventory_for_import.csv');

        if (! file_exists($csvPath)) {
            $this->command->error("CSV not found at: $csvPath");

            return;
        }

        $handle = fopen($csvPath, 'r');
        $headers = fgetcsv($handle);

        $totalItems = 0;
        $totalCost = 0;
        $productIds = [];

        $barcodeBase = 100000000000;

        while (($row = fgetcsv($handle)) !== false) {
            $data = array_combine($headers, $row);

            $category = $data['category'];
            $unitGroupName = $data['unit_group'];
            $name = $data['name'];
            $cost = (float) ($data['cost'] ?: 0);
            $price = (float) ($data['price'] ?: 0);
            $stock = (int) ($data['stock_qty'] ?: 0);
            $genericName = $data['generic_name'] ?? '';

            $categoryId = $this->categoryMap[$category] ?? $this->categoryMap['General'];
            $unitGroupId = $this->unitGroupMap[$unitGroupName] ?? $this->unitGroupMap['Pieces'];
            $baseUnitId = $this->unitMap[$unitGroupName] ?? $this->unitMap['Pieces'];

            $barcode = (string) ($barcodeBase + $totalItems);
            $sku = $this->generateSku($name);

            $product = Product::create([
                'name' => $name,
                'tax_type' => 'inclusive',
                'tax_group_id' => null,
                'tax_value' => 0,
                'product_type' => 'product',
                'type' => 'materialized',
                'accurate_tracking' => false,
                'auto_cogs' => true,
                'status' => 'available',
                'stock_management' => 'enabled',
                'barcode' => $barcode,
                'barcode_type' => 'code128',
                'sku' => $sku,
                'description' => $genericName ?: null,
                'thumbnail_id' => null,
                'category_id' => $categoryId,
                'parent_id' => 0,
                'unit_group' => $unitGroupId,
                'on_expiration' => 'prevent_sales',
                'expires' => false,
                'searchable' => true,
                'author_id' => $this->authorId,
                'uuid' => Str::uuid(),
                'position' => 0,
                'pinned' => false,
            ]);

            ProductUnitQuantity::create([
                'product_id' => $product->id,
                'type' => 'product',
                'preview_url' => null,
                'expiration_date' => null,
                'unit_id' => $baseUnitId,
                'barcode' => $barcode,
                'scale_plu' => null,
                'is_weighable' => false,
                'quantity' => $stock,
                'low_quantity' => 10,
                'stock_alert_enabled' => false,
                'sale_price' => $price,
                'sale_price_edit' => $price,
                'sale_price_net' => $price,
                'sale_price_gross' => $price,
                'sale_price_tax' => 0,
                'wholesale_price' => round($price * 0.85, 2),
                'wholesale_price_edit' => round($price * 0.85, 2),
                'wholesale_price_gross' => round($price * 0.85, 2),
                'wholesale_price_net' => round($price * 0.85, 2),
                'wholesale_price_tax' => 0,
                'custom_price' => 0,
                'custom_price_edit' => 0,
                'custom_price_gross' => 0,
                'custom_price_net' => 0,
                'custom_price_tax' => 0,
                'visible' => true,
                'convert_unit_id' => null,
                'cogs' => $cost,
                'uuid' => Str::uuid(),
            ]);

            // Create procurement product record for audit trail
            DB::table('nexopos_procurements_products')->insert([
                'name' => $name,
                'gross_purchase_price' => $cost,
                'net_purchase_price' => $cost,
                'procurement_id' => $procurement->id,
                'product_id' => $product->id,
                'purchase_price' => $cost,
                'quantity' => $stock,
                'available_quantity' => $stock,
                'tax_group_id' => null,
                'barcode' => $barcode,
                'expiration_date' => null,
                'tax_type' => 'inclusive',
                'tax_value' => 0,
                'total_purchase_price' => $cost * $stock,
                'unit_id' => $baseUnitId,
                'convert_unit_id' => null,
                'author_id' => $this->authorId,
                'uuid' => Str::uuid(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $totalItems++;
            $totalCost += $cost * $stock;
            $productIds[] = $product->id;

            if ($totalItems % 200 === 0) {
                echo "   ... {$totalItems} products imported\n";
            }
        }

        fclose($handle);

        // Update procurement totals
        $procurement->value = $totalCost;
        $procurement->cost = $totalCost;
        $procurement->total_items = $totalItems;
        $procurement->save();

        echo "   ✓ {$totalItems} products imported successfully\n";
        echo "   ✓ Total procurement value: PHP " . number_format($totalCost, 2) . "\n";
    }

    private function generateSku(string $name): string
    {
        $clean = preg_replace('/[^a-zA-Z0-9]/', '', $name);
        $prefix = strtoupper(substr($clean, 0, 5));
        $suffix = strtoupper(Str::random(4));
        $prefix = mb_substr($prefix, 0, 5);

        return $prefix . '-' . $suffix;
    }

    private function countCsvRows(): int
    {
        $csvPath = base_path('inventory_for_import.csv');
        if (! file_exists($csvPath)) {
            return 0;
        }
        $lines = file($csvPath);

        return count($lines) - 1;
    }
}
