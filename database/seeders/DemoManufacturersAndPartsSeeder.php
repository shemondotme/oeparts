<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Manufacturer;
use App\Models\MediaFile;
use App\Models\Product;
use App\Enums\ProductCondition;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoManufacturersAndPartsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $logoPath = base_path('media/Manufacturers Logo');
        
        // Define manufacturers based on available logos
        $manufacturersData = [
            ['name' => 'Alfa Romeo', 'slug' => 'alfa-romeo', 'logo_file' => 'Alfa-Romeo.png', 'country' => 'IT'],
            ['name' => 'Audi', 'slug' => 'audi', 'logo_file' => 'Audi.png', 'country' => 'DE'],
            ['name' => 'BMW', 'slug' => 'bmw', 'logo_file' => 'BMW.png', 'country' => 'DE'],
            ['name' => 'Ford', 'slug' => 'ford', 'logo_file' => 'Ford.png', 'country' => 'US'],
            ['name' => 'Mercedes-Benz', 'slug' => 'mercedes-benz', 'logo_file' => 'Mercedes-Benz.png', 'country' => 'DE'],
            ['name' => 'Opel', 'slug' => 'opel', 'logo_file' => 'Opel.png', 'country' => 'DE'],
            ['name' => 'Peugeot', 'slug' => 'peugeot', 'logo_file' => 'Peugeot.png', 'country' => 'FR'],
            ['name' => 'Toyota', 'slug' => 'toyota', 'logo_file' => 'Toyota.png', 'country' => 'JP'],
            ['name' => 'Volkswagen', 'slug' => 'volkswagen', 'logo_file' => 'Volkwagen.png', 'country' => 'DE'],
        ];

        DB::transaction(function () use ($manufacturersData, $logoPath) {
            // 1. Clear existing manufacturers and their products
            Product::onlyTrashed()->forceDelete();
            Product::query()->forceDelete();
            Manufacturer::query()->delete();

            // 2. Ensure we have an admin to associate with media files
            $admin = Admin::first();
            if (!$admin) {
                $admin = Admin::create([
                    'name' => 'System Admin',
                    'email' => 'system@oemhub.test',
                    'password' => Hash::make('password123'),
                    'is_active' => true,
                ]);
            }

            foreach ($manufacturersData as $data) {
                $logoId = null;
                $logoFilePath = $logoPath . '/' . $data['logo_file'];

                // 3. Upload Logo if exists
                if (file_exists($logoFilePath)) {
                    $fileName = $data['logo_file'];
                    $relativePath = 'logos/' . $fileName;
                    
                    // Ensure directory exists
                    if (!file_exists(public_path('storage/logos'))) {
                        mkdir(public_path('storage/logos'), 0755, true);
                    }
                    
                    // Copy file to public storage for demo purposes
                    copy($logoFilePath, public_path('storage/' . $relativePath));

                    $mediaFile = MediaFile::create([
                        'uploaded_by' => $admin->id,
                        'file_name' => $fileName,
                        'file_path' => $relativePath,
                        'file_url' => asset('storage/' . $relativePath),
                        'mime_type' => mime_content_type($logoFilePath),
                        'size' => filesize($logoFilePath),
                        'alt_text' => $data['name'] . ' Logo',
                    ]);
                    $logoId = $mediaFile->id;
                }

                // 4. Create Manufacturer
                $manufacturer = Manufacturer::create([
                    'name' => [
                        'en' => $data['name'],
                        'de' => $data['name'],
                        'lt' => $data['name'],
                        'fr' => $data['name'],
                        'es' => $data['name'],
                    ],
                    'slug' => $data['slug'],
                    'logo_id' => $logoId,
                    'country_code' => $data['country'],
                    'is_active' => true,
                    'is_verified_oem' => true,
                    'sort_order' => 0,
                ]);

                // 5. Create Demo Products for this Manufacturer
                $this->createDemoProducts($manufacturer);
            }
        });
    }

    private function createDemoProducts(Manufacturer $manufacturer): void
    {
        $oemPrefix = strtoupper(Str::substr($manufacturer->slug, 0, 3));

        // Create 10 demo products per manufacturer
        for ($i = 1; $i <= 10; $i++) {
            // SQLite test DB keeps legacy CHECK on `condition` unless MySQL-style ALTER ran; keep `new` only for portability.
            $condition = ProductCondition::New;
            $isInStock = $i % 2 === 0; // Alternate stock status
            $oemNumber = $oemPrefix . '-' . str_pad($i, 6, '0', STR_PAD_LEFT);
            
            // Normalize OEM
            $normalizedOem = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $oemNumber));

            Product::create([
                'manufacturer_id' => $manufacturer->id,
                'oem_number' => $oemNumber,
                'normalized_oem' => $normalizedOem,
                'name' => [
                    'en' => "Genuine {$manufacturer->name['en']} Part {$i}",
                    'de' => "Echtes {$manufacturer->name['de']} Teil {$i}",
                    'lt' => "Originalus {$manufacturer->name['lt']} dalys {$i}",
                    'fr' => "Pièce d'origine {$manufacturer->name['fr']} {$i}",
                    'es' => "Pieza original de {$manufacturer->name['es']} {$i}",
                ],
                'description' => [
                    'en' => "High quality OEM part from {$manufacturer->name['en']}. Condition: {$condition->name}.",
                    'de' => "Hochwertiges OEM-Teil von {$manufacturer->name['de']}. Zustand: {$condition->name}.",
                    'lt' => "Aukštos kokybės OEM dalis iš {$manufacturer->name['lt']}. Būklė: {$condition->name}.",
                    'fr' => "Pièce OEM de haute qualité de {$manufacturer->name['fr']}. État : {$condition->name}.",
                    'es' => "Pieza OEM de alta calidad de {$manufacturer->name['es']}. Condición: {$condition->name}.",
                ],
                'condition' => $condition,
                'price' => bcmul((string)rand(20, 500), '1.00', 2), // Random price between 20 and 500
                'delivery_time' => '2-4 days',
                'moq' => 1,
                'is_in_stock' => $isInStock,
                'is_active' => true,
            ]);
        }
    }
}
