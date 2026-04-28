<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductUnitQuantity;
use App\Models\Role;
use App\Models\Unit;
use App\Models\UnitGroup;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PharmacyProductSeeder extends Seeder
{
    /**
     * Run the pharmacy product seeds.
     *
     * @return void
     */
    public function run()
    {
        $authorId = Role::namespace('admin')->users->first()->id ?? 1;

        // Fetch all pharmacy categories
        $categories = ProductCategory::whereIn('name', [
            'Pain Relief',
            'Antibiotics',
            'Vitamins & Supplements',
            'Cough & Cold',
            'Allergy & Sinus',
            'Digestive Health',
            'Skin Care',
            'Eye & Ear Care',
            'First Aid',
            'Diabetes Care',
            'Heart Health',
            'Baby & Child Care',
            'Personal Care',
            'Medical Devices',
        ])->get()->keyBy('name');

        // Fetch unit groups and units
        $piecesGroup = UnitGroup::where('name', 'Pharmacy Pieces')->first();
        $liquidGroup = UnitGroup::where('name', 'Pharmacy Liquid')->first();
        $topicalGroup = UnitGroup::where('name', 'Pharmacy Topical')->first();

        $pieceUnit = Unit::where('group_id', $piecesGroup->id)->where('base_unit', true)->first();
        $bottleUnit = Unit::where('group_id', $liquidGroup->id)->where('base_unit', true)->first();
        $tubeUnit = Unit::where('group_id', $topicalGroup->id)->where('base_unit', true)->first();

        $medicines = $this->getMedicinesData();

        foreach ($medicines as $medicine) {
            $category = $categories->get($medicine['category']);

            if (! $category) {
                continue;
            }

            $unitGroup = $piecesGroup;
            $baseUnit = $pieceUnit;

            if (in_array($medicine['form'], ['Syrup', 'Suspension', 'Drops', 'Solution'])) {
                $unitGroup = $liquidGroup;
                $baseUnit = $bottleUnit;
            } elseif (in_array($medicine['form'], ['Cream', 'Ointment', 'Gel', 'Lotion'])) {
                $unitGroup = $topicalGroup;
                $baseUnit = $tubeUnit;
            }

            $barcode = $this->generateBarcode();
            $sku = $this->generateSku($medicine['name']);

            $product = Product::create([
                'name' => $medicine['name'],
                'tax_type' => 'inclusive',
                'tax_group_id' => null,
                'tax_value' => 0,
                'product_type' => 'product',
                'type' => 'tangible',
                'accurate_tracking' => false,
                'auto_cogs' => true,
                'status' => 'available',
                'stock_management' => 'enabled',
                'barcode' => $barcode,
                'barcode_type' => 'code128',
                'sku' => $sku,
                'description' => $medicine['description'] ?? null,
                'thumbnail_id' => null,
                'category_id' => $category->id,
                'parent_id' => 0,
                'unit_group' => $unitGroup->id,
                'on_expiration' => 'prevent_sales',
                'expires' => true,
                'searchable' => true,
                'author_id' => $authorId,
                'uuid' => Str::uuid(),
                'position' => 0,
                'pinned' => false,
            ]);

            $quantity = rand(10, 500);
            $lowQuantity = rand(5, 20);
            $salePrice = $medicine['price'];
            $cogs = $salePrice * 0.6;

            ProductUnitQuantity::create([
                'product_id' => $product->id,
                'type' => 'product',
                'preview_url' => null,
                'expiration_date' => $this->generateExpirationDate(),
                'unit_id' => $baseUnit->id,
                'barcode' => $barcode,
                'scale_plu' => null,
                'is_weighable' => false,
                'quantity' => $quantity,
                'low_quantity' => $lowQuantity,
                'stock_alert_enabled' => true,
                'sale_price' => $salePrice,
                'sale_price_edit' => $salePrice,
                'sale_price_net' => $salePrice,
                'sale_price_gross' => $salePrice,
                'sale_price_tax' => 0,
                'wholesale_price' => $salePrice * 0.85,
                'wholesale_price_edit' => $salePrice * 0.85,
                'wholesale_price_gross' => $salePrice * 0.85,
                'wholesale_price_net' => $salePrice * 0.85,
                'wholesale_price_tax' => 0,
                'custom_price' => 0,
                'custom_price_edit' => 0,
                'custom_price_gross' => 0,
                'custom_price_net' => 0,
                'custom_price_tax' => 0,
                'visible' => true,
                'convert_unit_id' => null,
                'cogs' => $cogs,
                'uuid' => Str::uuid(),
            ]);
        }
    }

    /**
     * Generate a random barcode.
     */
    private function generateBarcode(): string
    {
        return (string) rand(100000000000, 999999999999);
    }

    /**
     * Generate a SKU from the medicine name.
     */
    private function generateSku(string $name): string
    {
        $prefix = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $name), 0, 4));
        $suffix = strtoupper(Str::random(4));

        return $prefix . '-' . $suffix;
    }

    /**
     * Generate a random expiration date between 6 months and 3 years from now.
     */
    private function generateExpirationDate(): string
    {
        $months = rand(6, 36);
        return now()->addMonths($months)->format('Y-m-d H:i:s');
    }

    /**
     * Get the full list of medicine data.
     */
    private function getMedicinesData(): array
    {
        return [
            // Pain Relief
            ['name' => 'Biogesic 500mg Tablet', 'category' => 'Pain Relief', 'form' => 'Tablet', 'price' => 4.50, 'description' => 'Paracetamol for fever and mild pain relief.'],
            ['name' => 'Alaxan FR Capsule', 'category' => 'Pain Relief', 'form' => 'Capsule', 'price' => 8.75, 'description' => 'Ibuprofen + Paracetamol for muscle pain and headache.'],
            ['name' => 'Medicol 400mg Capsule', 'category' => 'Pain Relief', 'form' => 'Capsule', 'price' => 12.00, 'description' => 'Ibuprofen for pain and inflammation.'],
            ['name' => 'Dolfenal 500mg Tablet', 'category' => 'Pain Relief', 'form' => 'Tablet', 'price' => 18.50, 'description' => 'Mefenamic acid for menstrual and dental pain.'],
            ['name' => 'Advil 200mg Tablet', 'category' => 'Pain Relief', 'form' => 'Tablet', 'price' => 9.25, 'description' => 'Ibuprofen for headache and body pain.'],
            ['name' => 'Tylenol 500mg Tablet', 'category' => 'Pain Relief', 'form' => 'Tablet', 'price' => 6.50, 'description' => 'Paracetamol for fever and pain.'],
            ['name' => 'Aspirin 100mg Tablet', 'category' => 'Pain Relief', 'form' => 'Tablet', 'price' => 5.00, 'description' => 'Low-dose aspirin for pain and heart health.'],
            ['name' => 'Naproxen 250mg Tablet', 'category' => 'Pain Relief', 'form' => 'Tablet', 'price' => 15.00, 'description' => 'Naproxen sodium for arthritis and muscle pain.'],
            ['name' => 'Celecoxib 200mg Capsule', 'category' => 'Pain Relief', 'form' => 'Capsule', 'price' => 35.00, 'description' => 'COX-2 inhibitor for joint pain and inflammation.'],
            ['name' => 'Diclofenac 50mg Tablet', 'category' => 'Pain Relief', 'form' => 'Tablet', 'price' => 10.50, 'description' => 'NSAID for pain and inflammation relief.'],

            // Antibiotics
            ['name' => 'Amoxicillin 500mg Capsule', 'category' => 'Antibiotics', 'form' => 'Capsule', 'price' => 8.50, 'description' => 'Broad-spectrum antibiotic for bacterial infections.'],
            ['name' => 'Cefalexin 500mg Capsule', 'category' => 'Antibiotics', 'form' => 'Capsule', 'price' => 18.00, 'description' => 'Cephalosporin antibiotic for skin and respiratory infections.'],
            ['name' => 'Azithromycin 500mg Tablet', 'category' => 'Antibiotics', 'form' => 'Tablet', 'price' => 45.00, 'description' => 'Macrolide antibiotic for respiratory infections.'],
            ['name' => 'Ciprofloxacin 500mg Tablet', 'category' => 'Antibiotics', 'form' => 'Tablet', 'price' => 22.00, 'description' => 'Fluoroquinolone for urinary tract infections.'],
            ['name' => 'Metronidazole 500mg Tablet', 'category' => 'Antibiotics', 'form' => 'Tablet', 'price' => 12.50, 'description' => 'Antibiotic and antiprotozoal for anaerobic infections.'],
            ['name' => 'Co-Amoxiclav 625mg Tablet', 'category' => 'Antibiotics', 'form' => 'Tablet', 'price' => 28.00, 'description' => 'Amoxicillin + Clavulanic acid for resistant infections.'],
            ['name' => 'Doxycycline 100mg Capsule', 'category' => 'Antibiotics', 'form' => 'Capsule', 'price' => 16.50, 'description' => 'Tetracycline antibiotic for acne and infections.'],
            ['name' => 'Clarithromycin 500mg Tablet', 'category' => 'Antibiotics', 'form' => 'Tablet', 'price' => 55.00, 'description' => 'Macrolide antibiotic for H. pylori and respiratory infections.'],
            ['name' => 'Erythromycin 500mg Tablet', 'category' => 'Antibiotics', 'form' => 'Tablet', 'price' => 14.00, 'description' => 'Macrolide antibiotic for penicillin-allergic patients.'],
            ['name' => 'Levofloxacin 500mg Tablet', 'category' => 'Antibiotics', 'form' => 'Tablet', 'price' => 48.00, 'description' => 'Fluoroquinolone for pneumonia and sinusitis.'],

            // Vitamins & Supplements
            ['name' => 'Enervon C Tablet', 'category' => 'Vitamins & Supplements', 'form' => 'Tablet', 'price' => 9.50, 'description' => 'Vitamin C + Zinc for immune support.'],
            ['name' => 'Centrum Advance Tablet', 'category' => 'Vitamins & Supplements', 'form' => 'Tablet', 'price' => 12.00, 'description' => 'Complete multivitamin and mineral supplement.'],
            ['name' => 'Neurobion Tablet', 'category' => 'Vitamins & Supplements', 'form' => 'Tablet', 'price' => 14.50, 'description' => 'B-vitamins for nerve health and energy.'],
            ['name' => 'Calciumade Tablet', 'category' => 'Vitamins & Supplements', 'form' => 'Tablet', 'price' => 11.00, 'description' => 'Calcium + Vitamin D for bone health.'],
            ['name' => 'Fern-C Capsule', 'category' => 'Vitamins & Supplements', 'form' => 'Capsule', 'price' => 7.50, 'description' => 'Sodium ascorbate vitamin C.'],
            ['name' => 'Myra E 400IU Capsule', 'category' => 'Vitamins & Supplements', 'form' => 'Capsule', 'price' => 10.00, 'description' => 'Vitamin E for skin and antioxidant support.'],
            ['name' => 'Conzace Capsule', 'category' => 'Vitamins & Supplements', 'form' => 'Capsule', 'price' => 13.50, 'description' => 'Multivitamins with zinc for immunity.'],
            ['name' => 'Revicon Forte Tablet', 'category' => 'Vitamins & Supplements', 'form' => 'Tablet', 'price' => 8.00, 'description' => 'Multivitamins with iron for fatigue.'],
            ['name' => 'Omega-3 Fish Oil 1000mg', 'category' => 'Vitamins & Supplements', 'form' => 'Capsule', 'price' => 18.00, 'description' => 'Fish oil for heart and brain health.'],
            ['name' => 'Vitamin D3 1000IU', 'category' => 'Vitamins & Supplements', 'form' => 'Capsule', 'price' => 15.00, 'description' => 'Cholecalciferol for bone and immune health.'],
            ['name' => 'Folic Acid 5mg Tablet', 'category' => 'Vitamins & Supplements', 'form' => 'Tablet', 'price' => 5.50, 'description' => 'Essential for pregnancy and red blood cell formation.'],
            ['name' => 'Iron + Folic Acid Tablet', 'category' => 'Vitamins & Supplements', 'form' => 'Tablet', 'price' => 6.00, 'description' => 'For anemia prevention and blood health.'],

            // Cough & Cold
            ['name' => 'Neozep Forte Tablet', 'category' => 'Cough & Cold', 'form' => 'Tablet', 'price' => 7.00, 'description' => 'For colds, fever, and nasal congestion.'],
            ['name' => 'Bioflu 500mg Tablet', 'category' => 'Cough & Cold', 'form' => 'Tablet', 'price' => 8.50, 'description' => 'Paracetamol + Phenylephrine for flu symptoms.'],
            ['name' => 'Solmux 500mg Capsule', 'category' => 'Cough & Cold', 'form' => 'Capsule', 'price' => 16.00, 'description' => 'Carbocisteine for productive cough.'],
            ['name' => 'Ambroxol 30mg Tablet', 'category' => 'Cough & Cold', 'form' => 'Tablet', 'price' => 9.50, 'description' => 'Mucolytic for chest congestion.'],
            ['name' => 'Robitussin DM Syrup', 'category' => 'Cough & Cold', 'form' => 'Syrup', 'price' => 125.00, 'description' => 'Dextromethorphan + Guaifenesin cough syrup.'],
            ['name' => 'Benadryl Syrup', 'category' => 'Cough & Cold', 'form' => 'Syrup', 'price' => 145.00, 'description' => 'Diphenhydramine for cough and allergy relief.'],
            ['name' => 'Lagundi Syrup 120ml', 'category' => 'Cough & Cold', 'form' => 'Syrup', 'price' => 95.00, 'description' => 'Herbal cough remedy.'],
            ['name' => 'Mucosolvan Syrup 100ml', 'category' => 'Cough & Cold', 'form' => 'Syrup', 'price' => 135.00, 'description' => 'Ambroxol syrup for mucus clearance.'],
            ['name' => 'Decolgen Tablet', 'category' => 'Cough & Cold', 'form' => 'Tablet', 'price' => 6.50, 'description' => 'For colds, runny nose, and headache.'],
            ['name' => 'Sinutab Tablet', 'category' => 'Cough & Cold', 'form' => 'Tablet', 'price' => 10.00, 'description' => 'For sinus congestion and pressure.'],

            // Allergy & Sinus
            ['name' => 'Cetirizine 10mg Tablet', 'category' => 'Allergy & Sinus', 'form' => 'Tablet', 'price' => 8.00, 'description' => 'Antihistamine for allergies and hay fever.'],
            ['name' => 'Loratadine 10mg Tablet', 'category' => 'Allergy & Sinus', 'form' => 'Tablet', 'price' => 9.00, 'description' => 'Non-drowsy antihistamine for allergies.'],
            ['name' => 'Benadryl 25mg Capsule', 'category' => 'Allergy & Sinus', 'form' => 'Capsule', 'price' => 7.50, 'description' => 'Diphenhydramine for allergy relief.'],
            ['name' => 'Allerta 10mg Tablet', 'category' => 'Allergy & Sinus', 'form' => 'Tablet', 'price' => 12.00, 'description' => 'Loratadine for allergic rhinitis.'],
            ['name' => 'Claritin 10mg Tablet', 'category' => 'Allergy & Sinus', 'form' => 'Tablet', 'price' => 18.00, 'description' => 'Loratadine for 24-hour allergy relief.'],
            ['name' => 'Zyrtec 10mg Tablet', 'category' => 'Allergy & Sinus', 'form' => 'Tablet', 'price' => 22.00, 'description' => 'Cetirizine for allergy and hives.'],
            ['name' => 'Nasal Spray Saline 30ml', 'category' => 'Allergy & Sinus', 'form' => 'Solution', 'price' => 150.00, 'description' => 'Saline nasal spray for congestion relief.'],
            ['name' => 'Fluticasone Nasal Spray', 'category' => 'Allergy & Sinus', 'form' => 'Solution', 'price' => 350.00, 'description' => 'Steroid nasal spray for allergic rhinitis.'],
            ['name' => 'Montelukast 10mg Tablet', 'category' => 'Allergy & Sinus', 'form' => 'Tablet', 'price' => 28.00, 'description' => 'Leukotriene receptor antagonist for asthma and allergies.'],
            ['name' => 'Chlorpheniramine 4mg Tablet', 'category' => 'Allergy & Sinus', 'form' => 'Tablet', 'price' => 4.00, 'description' => 'Antihistamine for runny nose and sneezing.'],

            // Digestive Health
            ['name' => 'Kremil-S Tablet', 'category' => 'Digestive Health', 'form' => 'Tablet', 'price' => 10.50, 'description' => 'Antacid for hyperacidity and heartburn.'],
            ['name' => 'Gaviscon Tablet', 'category' => 'Digestive Health', 'form' => 'Tablet', 'price' => 12.00, 'description' => 'Alginate antacid for acid reflux.'],
            ['name' => 'Omeprazole 20mg Capsule', 'category' => 'Digestive Health', 'form' => 'Capsule', 'price' => 18.00, 'description' => 'PPI for GERD and stomach ulcers.'],
            ['name' => 'Loperamide 2mg Capsule', 'category' => 'Digestive Health', 'form' => 'Capsule', 'price' => 9.50, 'description' => 'Anti-diarrheal medicine.'],
            ['name' => 'Dicyclomine 10mg Tablet', 'category' => 'Digestive Health', 'form' => 'Tablet', 'price' => 11.00, 'description' => 'Antispasmodic for stomach cramps.'],
            ['name' => 'Buscopan 10mg Tablet', 'category' => 'Digestive Health', 'form' => 'Tablet', 'price' => 16.50, 'description' => 'Hyoscine butylbromide for abdominal pain.'],
            ['name' => 'Dulcolax 5mg Tablet', 'category' => 'Digestive Health', 'form' => 'Tablet', 'price' => 8.00, 'description' => 'Bisacodyl laxative for constipation.'],
            ['name' => 'Senna 8.6mg Tablet', 'category' => 'Digestive Health', 'form' => 'Tablet', 'price' => 6.50, 'description' => 'Natural laxative for constipation relief.'],
            ['name' => 'Simethicone 80mg Tablet', 'category' => 'Digestive Health', 'form' => 'Tablet', 'price' => 7.00, 'description' => 'Anti-gas medicine for bloating.'],
            ['name' => 'Ranitidine 150mg Tablet', 'category' => 'Digestive Health', 'form' => 'Tablet', 'price' => 8.50, 'description' => 'H2 blocker for acid reduction.'],
            ['name' => 'Lactulose Syrup 120ml', 'category' => 'Digestive Health', 'form' => 'Syrup', 'price' => 185.00, 'description' => 'Osmotic laxative for constipation.'],
            ['name' => 'Oral Rehydration Salts', 'category' => 'Digestive Health', 'form' => 'Sachet', 'price' => 15.00, 'description' => 'ORS for dehydration and diarrhea.'],

            // Skin Care
            ['name' => 'Betadine Solution 30ml', 'category' => 'Skin Care', 'form' => 'Solution', 'price' => 85.00, 'description' => 'Povidone-iodine antiseptic for wound cleaning.'],
            ['name' => 'Betadine Ointment 10g', 'category' => 'Skin Care', 'form' => 'Ointment', 'price' => 95.00, 'description' => 'Antiseptic ointment for minor cuts and burns.'],
            ['name' => 'Hydrocortisone 1% Cream', 'category' => 'Skin Care', 'form' => 'Cream', 'price' => 120.00, 'description' => 'Topical steroid for rashes and itching.'],
            ['name' => 'Clotrimazole 1% Cream', 'category' => 'Skin Care', 'form' => 'Cream', 'price' => 95.00, 'description' => 'Antifungal cream for athlete\'s foot and ringworm.'],
            ['name' => 'Mupirocin 2% Ointment', 'category' => 'Skin Care', 'form' => 'Ointment', 'price' => 185.00, 'description' => 'Antibiotic ointment for bacterial skin infections.'],
            ['name' => 'Calamine Lotion 60ml', 'category' => 'Skin Care', 'form' => 'Lotion', 'price' => 75.00, 'description' => 'Soothing lotion for rashes and insect bites.'],
            ['name' => 'Neosporin Ointment 5g', 'category' => 'Skin Care', 'form' => 'Ointment', 'price' => 110.00, 'description' => 'Triple antibiotic ointment for wound care.'],
            ['name' => 'Tretinoin 0.05% Cream', 'category' => 'Skin Care', 'form' => 'Cream', 'price' => 250.00, 'description' => 'Retinoid cream for acne and skin renewal.'],
            ['name' => 'Benzoyl Peroxide 5% Gel', 'category' => 'Skin Care', 'form' => 'Gel', 'price' => 180.00, 'description' => 'Acne treatment gel.'],
            ['name' => 'Silver Sulfadiazine Cream', 'category' => 'Skin Care', 'form' => 'Cream', 'price' => 220.00, 'description' => 'Burn treatment cream.'],

            // Eye & Ear Care
            ['name' => 'Eye Mo Drops 15ml', 'category' => 'Eye & Ear Care', 'form' => 'Drops', 'price' => 135.00, 'description' => 'Lubricating eye drops for dry eyes.'],
            ['name' => 'Visine Eye Drops 15ml', 'category' => 'Eye & Ear Care', 'form' => 'Drops', 'price' => 145.00, 'description' => 'Redness relief eye drops.'],
            ['name' => 'Chloramphenicol Eye Drops', 'category' => 'Eye & Ear Care', 'form' => 'Drops', 'price' => 95.00, 'description' => 'Antibiotic eye drops for infections.'],
            ['name' => 'Ofloxacin Eye Drops', 'category' => 'Eye & Ear Care', 'form' => 'Drops', 'price' => 165.00, 'description' => 'Antibiotic eye drops for bacterial infections.'],
            ['name' => 'Ciprofloxacin Ear Drops', 'category' => 'Eye & Ear Care', 'form' => 'Drops', 'price' => 155.00, 'description' => 'Antibiotic ear drops for otitis externa.'],
            ['name' => 'Carbamide Peroxide Ear Drops', 'category' => 'Eye & Ear Care', 'form' => 'Drops', 'price' => 125.00, 'description' => 'Ear wax removal drops.'],
            ['name' => 'Artificial Tears 10ml', 'category' => 'Eye & Ear Care', 'form' => 'Drops', 'price' => 110.00, 'description' => 'Lubricant eye drops for dry eye syndrome.'],
            ['name' => 'Tobramycin Eye Drops', 'category' => 'Eye & Ear Care', 'form' => 'Drops', 'price' => 175.00, 'description' => 'Antibiotic eye drops.'],

            // First Aid
            ['name' => 'Band-Aid Assorted Pack', 'category' => 'First Aid', 'form' => 'Pack', 'price' => 55.00, 'description' => 'Assorted sizes adhesive bandages.'],
            ['name' => 'Gauze Pads 4x4 Pack', 'category' => 'First Aid', 'form' => 'Pack', 'price' => 45.00, 'description' => 'Sterile gauze pads for wound dressing.'],
            ['name' => 'Elastic Bandage 4inch', 'category' => 'First Aid', 'form' => 'Roll', 'price' => 65.00, 'description' => 'Compression bandage for sprains.'],
            ['name' => 'Medical Tape 1inch', 'category' => 'First Aid', 'form' => 'Roll', 'price' => 35.00, 'description' => 'Hypoallergenic medical adhesive tape.'],
            ['name' => 'Cotton Balls 100pcs', 'category' => 'First Aid', 'form' => 'Pack', 'price' => 40.00, 'description' => 'Sterile cotton balls for wound care.'],
            ['name' => 'Alcohol Swabs 100pcs', 'category' => 'First Aid', 'form' => 'Pack', 'price' => 55.00, 'description' => 'Isopropyl alcohol prep pads.'],
            ['name' => 'Povidone-Iodine Swabs', 'category' => 'First Aid', 'form' => 'Pack', 'price' => 60.00, 'description' => 'Antiseptic swabs for wound cleaning.'],
            ['name' => 'Burn Gel 25g', 'category' => 'First Aid', 'form' => 'Tube', 'price' => 85.00, 'description' => 'Cooling gel for minor burns.'],
            ['name' => 'Tweezers Stainless Steel', 'category' => 'First Aid', 'form' => 'Piece', 'price' => 45.00, 'description' => 'Sterile tweezers for splinter removal.'],
            ['name' => 'Digital Thermometer', 'category' => 'First Aid', 'form' => 'Piece', 'price' => 150.00, 'description' => 'Fast-read digital thermometer.'],

            // Diabetes Care
            ['name' => 'Glucometer Kit', 'category' => 'Diabetes Care', 'form' => 'Kit', 'price' => 450.00, 'description' => 'Blood glucose monitoring system.'],
            ['name' => 'Test Strips 50pcs', 'category' => 'Diabetes Care', 'form' => 'Pack', 'price' => 550.00, 'description' => 'Blood glucose test strips.'],
            ['name' => 'Lancets 100pcs', 'category' => 'Diabetes Care', 'form' => 'Pack', 'price' => 120.00, 'description' => 'Sterile lancets for blood sampling.'],
            ['name' => 'Metformin 500mg Tablet', 'category' => 'Diabetes Care', 'form' => 'Tablet', 'price' => 6.50, 'description' => 'First-line medication for type 2 diabetes.'],
            ['name' => 'Gliclazide 80mg Tablet', 'category' => 'Diabetes Care', 'form' => 'Tablet', 'price' => 12.00, 'description' => 'Sulfonylurea for blood sugar control.'],
            ['name' => 'Glimepiride 2mg Tablet', 'category' => 'Diabetes Care', 'form' => 'Tablet', 'price' => 14.50, 'description' => 'Oral hypoglycemic agent.'],
            ['name' => 'Insulin Syringes 1ml', 'category' => 'Diabetes Care', 'form' => 'Pack', 'price' => 180.00, 'description' => 'U-40 insulin syringes pack of 10.'],
            ['name' => 'Alcohol Prep Pads 200pcs', 'category' => 'Diabetes Care', 'form' => 'Pack', 'price' => 85.00, 'description' => 'Sterile pads for injection site cleaning.'],

            // Heart Health
            ['name' => 'Amlodipine 5mg Tablet', 'category' => 'Heart Health', 'form' => 'Tablet', 'price' => 10.00, 'description' => 'Calcium channel blocker for hypertension.'],
            ['name' => 'Losartan 50mg Tablet', 'category' => 'Heart Health', 'form' => 'Tablet', 'price' => 12.50, 'description' => 'ARB for blood pressure control.'],
            ['name' => 'Metoprolol 50mg Tablet', 'category' => 'Heart Health', 'form' => 'Tablet', 'price' => 11.00, 'description' => 'Beta-blocker for hypertension and angina.'],
            ['name' => 'Simvastatin 20mg Tablet', 'category' => 'Heart Health', 'form' => 'Tablet', 'price' => 14.00, 'description' => 'Statin for cholesterol management.'],
            ['name' => 'Atorvastatin 20mg Tablet', 'category' => 'Heart Health', 'form' => 'Tablet', 'price' => 18.50, 'description' => 'Statin for LDL cholesterol reduction.'],
            ['name' => 'Aspirin 80mg Tablet', 'category' => 'Heart Health', 'form' => 'Tablet', 'price' => 4.50, 'description' => 'Low-dose aspirin for cardiovascular protection.'],
            ['name' => 'Clopidogrel 75mg Tablet', 'category' => 'Heart Health', 'form' => 'Tablet', 'price' => 22.00, 'description' => 'Antiplatelet for stroke prevention.'],
            ['name' => 'Isosorbide Dinitrate 10mg', 'category' => 'Heart Health', 'form' => 'Tablet', 'price' => 15.00, 'description' => 'Nitrate for angina relief.'],
            ['name' => 'Digoxin 0.25mg Tablet', 'category' => 'Heart Health', 'form' => 'Tablet', 'price' => 16.00, 'description' => 'Cardiac glycoside for heart failure.'],
            ['name' => 'Furosemide 40mg Tablet', 'category' => 'Heart Health', 'form' => 'Tablet', 'price' => 8.50, 'description' => 'Diuretic for fluid retention and hypertension.'],

            // Baby & Child Care
            ['name' => 'Paracetamol 120mg/5ml Syrup', 'category' => 'Baby & Child Care', 'form' => 'Syrup', 'price' => 85.00, 'description' => 'Pediatric fever and pain relief syrup.'],
            ['name' => 'Pediatric Cough Syrup 60ml', 'category' => 'Baby & Child Care', 'form' => 'Syrup', 'price' => 95.00, 'description' => 'Gentle cough relief for children.'],
            ['name' => 'Zinc Drops 20ml', 'category' => 'Baby & Child Care', 'form' => 'Drops', 'price' => 165.00, 'description' => 'Zinc supplement for immune support.'],
            ['name' => 'Vitamin D Drops 15ml', 'category' => 'Baby & Child Care', 'form' => 'Drops', 'price' => 185.00, 'description' => 'Pediatric vitamin D supplement.'],
            ['name' => 'Diaper Rash Cream 30g', 'category' => 'Baby & Child Care', 'form' => 'Cream', 'price' => 120.00, 'description' => 'Zinc oxide cream for diaper rash.'],
            ['name' => 'Baby Wipes 80pcs', 'category' => 'Baby & Child Care', 'form' => 'Pack', 'price' => 75.00, 'description' => 'Gentle alcohol-free baby wipes.'],
            ['name' => 'Nasal Aspirator', 'category' => 'Baby & Child Care', 'form' => 'Piece', 'price' => 150.00, 'description' => 'Bulb syringe for nasal congestion.'],
            ['name' => 'Teething Gel 10g', 'category' => 'Baby & Child Care', 'form' => 'Gel', 'price' => 135.00, 'description' => 'Soothing gel for teething pain.'],
            ['name' => 'Pedialyte Oral Solution', 'category' => 'Baby & Child Care', 'form' => 'Bottle', 'price' => 95.00, 'description' => 'Oral rehydration for children.'],
            ['name' => 'Infant Probiotic Drops', 'category' => 'Baby & Child Care', 'form' => 'Drops', 'price' => 350.00, 'description' => 'Probiotic supplement for digestive health.'],

            // Personal Care
            ['name' => 'Alcohol 70% 500ml', 'category' => 'Personal Care', 'form' => 'Bottle', 'price' => 85.00, 'description' => 'Isopropyl alcohol for disinfection.'],
            ['name' => 'Hand Sanitizer 250ml', 'category' => 'Personal Care', 'form' => 'Bottle', 'price' => 95.00, 'description' => 'Alcohol-based hand sanitizer gel.'],
            ['name' => 'Mouthwash 250ml', 'category' => 'Personal Care', 'form' => 'Bottle', 'price' => 125.00, 'description' => 'Antiseptic mouthwash for oral hygiene.'],
            ['name' => 'Toothpaste 150g', 'category' => 'Personal Care', 'form' => 'Tube', 'price' => 85.00, 'description' => 'Fluoride toothpaste for cavity protection.'],
            ['name' => 'Dental Floss 50m', 'category' => 'Personal Care', 'form' => 'Pack', 'price' => 55.00, 'description' => 'Waxed dental floss.'],
            ['name' => 'Feminine Wash 150ml', 'category' => 'Personal Care', 'form' => 'Bottle', 'price' => 110.00, 'description' => 'Gentle feminine hygiene wash.'],
            ['name' => 'Antiperspirant Roll-on', 'category' => 'Personal Care', 'form' => 'Bottle', 'price' => 95.00, 'description' => '24-hour protection deodorant.'],
            ['name' => 'Wet Wipes Antibacterial', 'category' => 'Personal Care', 'form' => 'Pack', 'price' => 65.00, 'description' => 'Antibacterial cleansing wipes.'],
            ['name' => 'Cotton Swabs 200pcs', 'category' => 'Personal Care', 'form' => 'Pack', 'price' => 45.00, 'description' => 'Double-tipped cotton swabs.'],
            ['name' => 'Disposable Face Masks 50pcs', 'category' => 'Personal Care', 'form' => 'Pack', 'price' => 150.00, 'description' => '3-ply surgical face masks.'],

            // Medical Devices
            ['name' => 'Digital BP Monitor', 'category' => 'Medical Devices', 'form' => 'Piece', 'price' => 1200.00, 'description' => 'Automatic upper arm blood pressure monitor.'],
            ['name' => 'Pulse Oximeter', 'category' => 'Medical Devices', 'form' => 'Piece', 'price' => 450.00, 'description' => 'Fingertip oxygen saturation monitor.'],
            ['name' => 'Nebulizer Machine', 'category' => 'Medical Devices', 'form' => 'Piece', 'price' => 850.00, 'description' => 'Compressor nebulizer for respiratory therapy.'],
            ['name' => 'Hot Water Bottle', 'category' => 'Medical Devices', 'form' => 'Piece', 'price' => 180.00, 'description' => 'Rubber hot water bottle for pain relief.'],
            ['name' => 'Ice Pack Reusable', 'category' => 'Medical Devices', 'form' => 'Piece', 'price' => 120.00, 'description' => 'Reusable cold therapy pack.'],
            ['name' => 'Heating Pad', 'category' => 'Medical Devices', 'form' => 'Piece', 'price' => 550.00, 'description' => 'Electric heating pad for muscle pain.'],
            ['name' => 'Wheelchair Standard', 'category' => 'Medical Devices', 'form' => 'Piece', 'price' => 4500.00, 'description' => 'Standard manual wheelchair.'],
            ['name' => 'Walking Cane Adjustable', 'category' => 'Medical Devices', 'form' => 'Piece', 'price' => 350.00, 'description' => 'Height-adjustable aluminum walking cane.'],
            ['name' => 'Infrared Thermometer', 'category' => 'Medical Devices', 'form' => 'Piece', 'price' => 650.00, 'description' => 'Non-contact forehead thermometer.'],
            ['name' => 'Stethoscope', 'category' => 'Medical Devices', 'form' => 'Piece', 'price' => 450.00, 'description' => 'Dual-head stethoscope for medical use.'],
        ];
    }
}
