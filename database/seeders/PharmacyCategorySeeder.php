<?php

namespace Database\Seeders;

use App\Models\ProductCategory;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PharmacyCategorySeeder extends Seeder
{
    /**
     * Medicine categories for a pharmacy.
     *
     * @return void
     */
    public function run()
    {
        $authorId = Role::namespace('admin')->users->first()->id ?? 1;

        $categories = [
            [
                'name' => 'Pain Relief',
                'description' => 'Medicines for pain management including headaches, muscle pain, and fever.',
            ],
            [
                'name' => 'Antibiotics',
                'description' => 'Prescription and over-the-counter antibiotics for bacterial infections.',
            ],
            [
                'name' => 'Vitamins & Supplements',
                'description' => 'Essential vitamins, minerals, and dietary supplements.',
            ],
            [
                'name' => 'Cough & Cold',
                'description' => 'Remedies for cough, colds, flu, and respiratory symptoms.',
            ],
            [
                'name' => 'Allergy & Sinus',
                'description' => 'Antihistamines and decongestants for allergies and sinus relief.',
            ],
            [
                'name' => 'Digestive Health',
                'description' => 'Medicines for stomach issues, acid reflux, and digestive support.',
            ],
            [
                'name' => 'Skin Care',
                'description' => 'Topical treatments, ointments, and creams for skin conditions.',
            ],
            [
                'name' => 'Eye & Ear Care',
                'description' => 'Drops and treatments for eye and ear conditions.',
            ],
            [
                'name' => 'First Aid',
                'description' => 'Bandages, antiseptics, and emergency medical supplies.',
            ],
            [
                'name' => 'Diabetes Care',
                'description' => 'Blood sugar monitors, test strips, and diabetic supplies.',
            ],
            [
                'name' => 'Heart Health',
                'description' => 'Medications for blood pressure, cholesterol, and cardiovascular health.',
            ],
            [
                'name' => 'Baby & Child Care',
                'description' => 'Pediatric medicines, vitamins, and care products for children.',
            ],
            [
                'name' => 'Personal Care',
                'description' => 'Hygiene products, oral care, and personal wellness items.',
            ],
            [
                'name' => 'Medical Devices',
                'description' => 'Thermometers, blood pressure monitors, and other medical equipment.',
            ],
        ];

        $createdCategories = [];

        foreach ($categories as $category) {
            $createdCategories[] = ProductCategory::create([
                'name' => $category['name'],
                'description' => $category['description'],
                'author_id' => $authorId,
                'uuid' => Str::uuid(),
                'displays_on_pos' => true,
                'parent_id' => 0,
                'position' => 0,
            ]);
        }

        return $createdCategories;
    }
}
