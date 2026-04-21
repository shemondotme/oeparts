<?php

namespace Database\Seeders;

use App\Models\Carrier;
use Illuminate\Database\Seeder;

class CarriersSeeder extends Seeder
{
    public function run(): void
    {
        $carriers = [
            [
                'name'         => 'DHL',
                'tracking_url' => 'https://www.dhl.com/en/express/tracking.html?AWB={tracking_number}',
                'is_active'    => true,
                'sort_order'   => 1,
            ],
            [
                'name'         => 'DPD',
                'tracking_url' => 'https://tracking.dpd.de/status/en_DE/parcel/{tracking_number}',
                'is_active'    => true,
                'sort_order'   => 2,
            ],
            [
                'name'         => 'GLS',
                'tracking_url' => 'https://gls-group.eu/track/{tracking_number}',
                'is_active'    => true,
                'sort_order'   => 3,
            ],
            [
                'name'         => 'FedEx',
                'tracking_url' => 'https://www.fedex.com/en-us/tracking.html?tracknumbers={tracking_number}',
                'is_active'    => true,
                'sort_order'   => 4,
            ],
            [
                'name'         => 'UPS',
                'tracking_url' => 'https://www.ups.com/track?tracknum={tracking_number}',
                'is_active'    => true,
                'sort_order'   => 5,
            ],
            [
                'name'         => 'Omniva',
                'tracking_url' => 'https://www.omniva.ee/era/jaljita?barcode={tracking_number}',
                'is_active'    => true,
                'sort_order'   => 6,
            ],
            [
                'name'         => 'LP Express',
                'tracking_url' => 'https://www.lpexpress.lt/sekimas?barcode={tracking_number}',
                'is_active'    => true,
                'sort_order'   => 7,
            ],
            [
                'name'         => 'Venipak',
                'tracking_url' => 'https://venipak.com/track/?code={tracking_number}',
                'is_active'    => true,
                'sort_order'   => 8,
            ],
        ];

        foreach ($carriers as $carrier) {
            Carrier::updateOrCreate(
                ['name' => $carrier['name']],
                $carrier
            );
        }
    }
}
