<?php

namespace Database\Seeders;

use App\Models\Manufacturer;
use App\Models\Product;
use App\Models\ProductCrossReference;
use App\Models\CarModel;
use Illuminate\Database\Seeder;

class TestProductSeeder extends Seeder
{
    /**
     * Sample OEM numbers from real manufacturers (VW, BMW, Mercedes, Bosch, etc.)
     */
    private const SAMPLE_PRODUCTS = [
        // ============================================
        // BMW 12137588837 - Ignition Coil (Main Test Number)
        // Multiple entries to test search filters
        // ============================================

        // BMW - New condition, in stock
        [
            'oem' => '12137588837',
            'manufacturer' => 'BMW',
            'country' => 'DE',
            'price' => 215.00,
            'condition' => 'new',
            'in_stock' => true,
            'description' => 'Ignition Coil',
        ],
        // BMW - New condition, out of stock
        [
            'oem' => '12137588837',
            'manufacturer' => 'BMW',
            'country' => 'DE',
            'price' => 225.00,
            'condition' => 'new',
            'in_stock' => false,
            'description' => 'Ignition Coil - Premium Grade',
        ],
        // BMW - Used condition, in stock
        [
            'oem' => '12137588837',
            'manufacturer' => 'BMW',
            'country' => 'DE',
            'price' => 145.00,
            'condition' => 'used',
            'in_stock' => true,
            'description' => 'Ignition Coil - Tested & Working',
        ],
        // BMW - Used condition, out of stock
        [
            'oem' => '12137588837',
            'manufacturer' => 'BMW',
            'country' => 'DE',
            'price' => 135.00,
            'condition' => 'used',
            'in_stock' => false,
            'description' => 'Ignition Coil - Grade A Used',
        ],

        // Audi - Same part number (cross-compatible)
        [
            'oem' => '12137588837',
            'manufacturer' => 'Audi',
            'country' => 'DE',
            'price' => 235.00,
            'condition' => 'new',
            'in_stock' => true,
            'description' => 'Ignition Coil (Audi Compatible)',
        ],
        [
            'oem' => '12137588837',
            'manufacturer' => 'Audi',
            'country' => 'DE',
            'price' => 155.00,
            'condition' => 'used',
            'in_stock' => true,
            'description' => 'Ignition Coil - Used OEM',
        ],

        // Mercedes-Benz
        [
            'oem' => '12137588837',
            'manufacturer' => 'Mercedes-Benz',
            'country' => 'DE',
            'price' => 245.00,
            'condition' => 'new',
            'in_stock' => true,
            'description' => 'Ignition Coil Assembly',
        ],
        [
            'oem' => '12137588837',
            'manufacturer' => 'Mercedes-Benz',
            'country' => 'DE',
            'price' => 165.00,
            'condition' => 'used',
            'in_stock' => false,
            'description' => 'Ignition Coil - Refurbished',
        ],

        // Volkswagen
        [
            'oem' => '12137588837',
            'manufacturer' => 'Volkswagen',
            'country' => 'DE',
            'price' => 198.00,
            'condition' => 'new',
            'in_stock' => true,
            'description' => 'Ignition Coil Unit',
        ],
        [
            'oem' => '12137588837',
            'manufacturer' => 'Volkswagen',
            'country' => 'DE',
            'price' => 125.00,
            'condition' => 'used',
            'in_stock' => true,
            'description' => 'Ignition Coil - Used Good Condition',
        ],

        // Ford
        [
            'oem' => '12137588837',
            'manufacturer' => 'Ford',
            'country' => 'US',
            'price' => 189.00,
            'condition' => 'new',
            'in_stock' => true,
            'description' => 'Ignition Coil Module',
        ],
        [
            'oem' => '12137588837',
            'manufacturer' => 'Ford',
            'country' => 'US',
            'price' => 115.00,
            'condition' => 'used',
            'in_stock' => true,
            'description' => 'Ignition Coil - Tested',
        ],

        // Toyota
        [
            'oem' => '12137588837',
            'manufacturer' => 'Toyota',
            'country' => 'JP',
            'price' => 205.00,
            'condition' => 'new',
            'in_stock' => false,
            'description' => 'Ignition Coil Assembly',
        ],
        [
            'oem' => '12137588837',
            'manufacturer' => 'Toyota',
            'country' => 'JP',
            'price' => 138.00,
            'condition' => 'used',
            'in_stock' => true,
            'description' => 'Ignition Coil - OEM Used',
        ],

        // Volvo
        [
            'oem' => '12137588837',
            'manufacturer' => 'Volvo',
            'country' => 'SE',
            'price' => 255.00,
            'condition' => 'new',
            'in_stock' => true,
            'description' => 'Ignition Coil - Volvo Spec',
        ],
        [
            'oem' => '12137588837',
            'manufacturer' => 'Volvo',
            'country' => 'SE',
            'price' => 175.00,
            'condition' => 'used',
            'in_stock' => false,
            'description' => 'Ignition Coil - Used Volvo OEM',
        ],

        // Opel
        [
            'oem' => '12137588837',
            'manufacturer' => 'Opel',
            'country' => 'DE',
            'price' => 178.00,
            'condition' => 'new',
            'in_stock' => true,
            'description' => 'Ignition Coil Unit',
        ],
        [
            'oem' => '12137588837',
            'manufacturer' => 'Opel',
            'country' => 'DE',
            'price' => 108.00,
            'condition' => 'used',
            'in_stock' => true,
            'description' => 'Ignition Coil - Budget Used',
        ],

        // Peugeot
        [
            'oem' => '12137588837',
            'manufacturer' => 'Peugeot',
            'country' => 'FR',
            'price' => 192.00,
            'condition' => 'new',
            'in_stock' => true,
            'description' => 'Ignition Coil - Peugeot Compatible',
        ],
        [
            'oem' => '12137588837',
            'manufacturer' => 'Peugeot',
            'country' => 'FR',
            'price' => 128.00,
            'condition' => 'used',
            'in_stock' => false,
            'description' => 'Ignition Coil - Used Grade B',
        ],

        // Alfa Romeo
        [
            'oem' => '12137588837',
            'manufacturer' => 'Alfa Romeo',
            'country' => 'IT',
            'price' => 268.00,
            'condition' => 'new',
            'in_stock' => true,
            'description' => 'Ignition Coil - Alfa Romeo Spec',
        ],
        [
            'oem' => '12137588837',
            'manufacturer' => 'Alfa Romeo',
            'country' => 'IT',
            'price' => 185.00,
            'condition' => 'used',
            'in_stock' => true,
            'description' => 'Ignition Coil - Used Italian OEM',
        ],

        // ============================================
        // Other BMW Parts (for variety)
        // ============================================
        [
            'oem' => '12137594936',
            'manufacturer' => 'BMW',
            'country' => 'DE',
            'price' => 198.75,
            'condition' => 'new',
            'in_stock' => true,
            'description' => 'Ignition Coil',
        ],
        [
            'oem' => '13647588837',
            'manufacturer' => 'BMW',
            'country' => 'DE',
            'price' => 325.00,
            'condition' => 'new',
            'in_stock' => false,
            'description' => 'Fuel Pump',
        ],
        [
            'oem' => '34346795532',
            'manufacturer' => 'BMW',
            'country' => 'DE',
            'price' => 445.00,
            'condition' => 'new',
            'in_stock' => true,
            'description' => 'Brake Caliper',
        ],
        // Mercedes-Benz
        [
            'oem' => 'A2769060000',
            'manufacturer' => 'Mercedes-Benz',
            'country' => 'DE',
            'price' => 289.00,
            'condition' => 'new',
            'in_stock' => true,
            'description' => 'Fuel Injector',
        ],
        [
            'oem' => 'A2749060000',
            'manufacturer' => 'Mercedes-Benz',
            'country' => 'DE',
            'price' => 265.50,
            'condition' => 'new',
            'in_stock' => true,
            'description' => 'Fuel Injector',
        ],
        [
            'oem' => 'A0009054301',
            'manufacturer' => 'Mercedes-Benz',
            'country' => 'DE',
            'price' => 175.00,
            'condition' => 'new',
            'in_stock' => true,
            'description' => 'Spark Plug',
        ],
        [
            'oem' => 'A2129006101',
            'manufacturer' => 'Mercedes-Benz',
            'country' => 'DE',
            'price' => 520.00,
            'condition' => 'used',
            'in_stock' => true,
            'description' => 'Headlight Assembly',
        ],
        // Bosch
        [
            'oem' => '0280158837',
            'manufacturer' => 'Bosch',
            'country' => 'DE',
            'price' => 95.00,
            'condition' => 'new',
            'in_stock' => true,
            'description' => 'Fuel Injector',
        ],
        [
            'oem' => '0280158829',
            'manufacturer' => 'Bosch',
            'country' => 'DE',
            'price' => 88.50,
            'condition' => 'new',
            'in_stock' => true,
            'description' => 'Fuel Injector',
        ],
        [
            'oem' => '0250205012',
            'manufacturer' => 'Bosch',
            'country' => 'DE',
            'price' => 42.00,
            'condition' => 'new',
            'in_stock' => true,
            'description' => 'Glow Plug',
        ],
        [
            'oem' => '0986494100',
            'manufacturer' => 'Bosch',
            'country' => 'DE',
            'price' => 65.00,
            'condition' => 'new',
            'in_stock' => true,
            'description' => 'Brake Pad Set',
        ],
        // Denso
        [
            'oem' => 'DNS1008',
            'manufacturer' => 'Denso',
            'country' => 'JP',
            'price' => 110.00,
            'condition' => 'new',
            'in_stock' => true,
            'description' => 'Fuel Injector',
        ],
        [
            'oem' => 'DNS1009',
            'manufacturer' => 'Denso',
            'country' => 'JP',
            'price' => 105.00,
            'condition' => 'new',
            'in_stock' => false,
            'description' => 'Fuel Injector',
        ],
        // Delphi
        [
            'oem' => 'DL10012',
            'manufacturer' => 'Delphi',
            'country' => 'GB',
            'price' => 78.00,
            'condition' => 'new',
            'in_stock' => true,
            'description' => 'Fuel Injector',
        ],
    ];

    /**
     * Cross-references (alternative OEM numbers)
     */
    private const CROSS_REFERENCES = [
        '12137588837' => [
            '12-13-7-588-837',
            'BMW12137588837',
            '12.13.7.588.837',
            '1213 7588 837',
            '12137588837A',
            '12137588837-BMW',
            'IC-12137588837',
            'BMW-IC-588837',
        ],
        '12137594936' => ['12-13-7-594-936', 'BMW12137594936'],
        '13647588837' => ['13-64-7-588-837', 'BMW13647588837'],
        '34346795532' => ['34-34-6-795-532', 'BMW34346795532'],
        'A2769060000' => ['A-276-906-00-00', '2769060000'],
        'A2749060000' => ['A-274-906-00-00', '2749060000'],
        'A0009054301' => ['A-000-905-43-01', '2749054301'],
        'A2129006101' => ['A-212-900-61-01', '2129006101'],
        '0280158837' => ['0-280-158-837', 'BOSCH0280158837'],
        '0280158829' => ['0-280-158-829', 'BOSCH0280158829'],
        '0250205012' => ['0-250-205-012', 'BOSCH0250205012'],
        '0986494100' => ['0-986-494-100', 'BOSCH0986494100'],
        'DNS1008' => ['DENSO-DNS1008', 'DENSO1008'],
        'DNS1009' => ['DENSO-DNS1009', 'DENSO1009'],
        'DL10012' => ['DELPHI-DL10012', 'DELPHI10012'],
    ];

    public function run(): void
    {
        echo "Seeding test products...\n";

        $manufacturers = [];
        $carModels = [];

        foreach (self::SAMPLE_PRODUCTS as $index => $productData) {
            // Create or get manufacturer
            $mfgKey = $productData['manufacturer'];
            if (!isset($manufacturers[$mfgKey])) {
                $manufacturers[$mfgKey] = Manufacturer::firstOrCreate(
                    ['slug' => strtolower(str_replace(' ', '-', $mfgKey))],
                    [
                        'name' => [
                            'en' => $mfgKey,
                            'de' => $mfgKey,
                            'lt' => $mfgKey,
                            'fr' => $mfgKey,
                            'es' => $mfgKey,
                        ],
                        'country_code' => $productData['country'],
                        'is_active' => true,
                    ]
                );
                echo "  ✓ Manufacturer: {$mfgKey}\n";
            }

            $manufacturer = $manufacturers[$mfgKey];

            // Create car models for first few products per manufacturer
            if (!isset($carModels[$mfgKey])) {
                $carModels[$mfgKey] = [
                    CarModel::firstOrCreate(
                        ['manufacturer_id' => $manufacturer->id, 'slug' => 'golf-mk7'],
                        ['name' => 'Golf Mk7', 'is_active' => true]
                    ),
                    CarModel::firstOrCreate(
                        ['manufacturer_id' => $manufacturer->id, 'slug' => 'passat-b8'],
                        ['name' => 'Passat B8', 'is_active' => true]
                    ),
                    CarModel::firstOrCreate(
                        ['manufacturer_id' => $manufacturer->id, 'slug' => '3-series-e90'],
                        ['name' => '3 Series E90', 'is_active' => true]
                    ),
                ];
            }

            // Create product
            $product = Product::create([
                'manufacturer_id' => $manufacturer->id,
                'oem_number' => $productData['oem'],
                'normalized_oem' => strtoupper(preg_replace('/[^A-Z0-9]/i', '', $productData['oem'])),
                'condition' => $productData['condition'],
                'price' => $productData['price'],
                'is_in_stock' => $productData['in_stock'],
                'is_active' => true,
                'delivery_time' => $productData['in_stock'] ? '2-3 days' : null,
            ]);

            // Attach car models (random 1-2)
            $attachCount = rand(1, min(2, count($carModels[$mfgKey])));
            $attachModels = array_slice($carModels[$mfgKey], 0, $attachCount);
            foreach ($attachModels as $model) {
                $product->carModels()->attach($model->id);
            }

            echo "  ✓ Product: {$productData['oem']} - {$productData['description']} (€{$productData['price']})\n";

            // Create cross-references
            if (isset(self::CROSS_REFERENCES[$productData['oem']])) {
                foreach (self::CROSS_REFERENCES[$productData['oem']] as $crossOem) {
                    ProductCrossReference::create([
                        'product_id' => $product->id,
                        'cross_oem_number' => $crossOem,
                        'normalized_cross_oem' => strtoupper(preg_replace('/[^A-Z0-9]/i', '', $crossOem)),
                    ]);
                }
            }
        }

        echo "\n✅ Seeding complete!\n";
        echo "\n📋 Test these OEM numbers:\n";
        echo "   - Main test number: 12137588837 (20+ results across 10 manufacturers)\n";
        echo "   - Cross-reference: 12-13-7-588-837, BMW12137588837, 12.13.7.588.837\n";
        echo "   - Partial match: 1213758, 7588837\n";
        echo "\n🔍 Filter Testing with 12137588837:\n";
        echo "   - By Manufacturer: BMW, Audi, Mercedes-Benz, VW, Ford, Toyota, Volvo, Opel, Peugeot, Alfa Romeo\n";
        echo "   - By Condition: new, used\n";
        echo "   - By Stock: in stock, out of stock\n";
        echo "   - By Price Range: €108 - €268\n";
        echo "\n📦 Other test numbers:\n";
        echo "   - BMW: 12137594936, 13647588837, 34346795532\n";
        echo "   - Mercedes: A2769060000, A2749060000, A0009054301, A2129006101\n";
        echo "   - Bosch: 0280158837, 0280158829, 0250205012, 0986494100\n";
        echo "   - Denso: DNS1008, DNS1009\n";
        echo "   - Delphi: DL10012\n";
        echo "   - No results: NONEXISTENT123, XYZ999999\n";
    }
}
