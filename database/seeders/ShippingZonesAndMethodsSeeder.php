<?php

namespace Database\Seeders;

use App\Models\ShippingCountry;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use App\Services\ViesService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds a single "Europe" shipping zone covering every country in
 * ViesService::getEuCountries() (the same 32-country EU/EEA list used by
 * TaxRatesSeeder and the manufacturer-country dropdown), with 3 flat-rate
 * methods attached.
 *
 * This previously claimed (in this docblock) to seed a 6-zone pan-European
 * matrix (EU Core / Western / Nordic+Baltic / Southern+Islands / UK+Switzerland
 * / Extended Europe) but never actually created any ShippingCountry rows —
 * it truncated the table and left it empty. Confirmed live: with zero
 * countries assigned to any zone, ShippingService::getMethodsForCountry()
 * never matches, so CheckoutController::buildShippingOptions() silently
 * fell back to "every active method, regardless of country" for every
 * customer — checkout still worked, but per-zone/per-country shipping
 * rules had no effect at all. Idempotent: truncates and re-seeds.
 */
class ShippingZonesAndMethodsSeeder extends Seeder
{
    public function run(): void
    {
        try {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        } catch (\Throwable) {
            //
        }
        ShippingMethod::truncate();
        ShippingCountry::truncate();
        ShippingZone::truncate();
        try {
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        } catch (\Throwable) {
            //
        }

        $zone = ShippingZone::create([
            'name'       => 'Europe',
            'is_active'  => true,
            'sort_order' => 10,
        ]);

        foreach (ViesService::getEuCountries() as $code => $name) {
            ShippingCountry::create([
                'zone_id'      => $zone->id,
                'country_code' => $code,
                'country_name' => $name,
            ]);
        }

        $methods = [
            [
                'name'    => ['en' => 'Express Shipping', 'de' => 'Express-Versand', 'lt' => 'Express siunta', 'fr' => 'Livraison express', 'es' => 'Envío exprés'],
                'desc'    => ['en' => 'Estimated delivery within 3–5 business days.', 'de' => 'Voraussichtliche Lieferung innerhalb von 3–5 Werktagen.', 'lt' => 'Numatomas pristatymas per 3–5 darbo dienas.', 'fr' => 'Livraison estimée sous 3 à 5 jours ouvrés.', 'es' => 'Entrega estimada en 3–5 días hábiles.'],
                'rate' => 75.00, 'free_over' => null, 'min_d' => 3, 'max_d' => 5, 'sort' => 10,
            ],
            [
                'name'    => ['en' => 'Standard Shipping', 'de' => 'Standard-Versand', 'lt' => 'Standartinė siunta', 'fr' => 'Livraison standard', 'es' => 'Envío estándar'],
                'desc'    => ['en' => 'Estimated delivery within 5–7 business days.', 'de' => 'Voraussichtliche Lieferung innerhalb von 5–7 Werktagen.', 'lt' => 'Numatomas pristatymas per 5–7 darbo dienas.', 'fr' => 'Livraison estimée sous 5 à 7 jours ouvrés.', 'es' => 'Entrega estimada en 5–7 días hábiles.'],
                'rate' => 40.00, 'free_over' => null, 'min_d' => 5, 'max_d' => 7, 'sort' => 20,
            ],
            [
                'name'    => ['en' => 'Economy Shipping', 'de' => 'Wirtschaftsversand', 'lt' => 'Ekonomiška siunta', 'fr' => 'Livraison économique', 'es' => 'Envío económico'],
                'desc'    => ['en' => 'Estimated delivery within up to 15 business days.', 'de' => 'Voraussichtliche Lieferung innerhalb von bis zu 15 Werktagen.', 'lt' => 'Numatomas pristatymas per iki 15 darbo dienų.', 'fr' => 'Livraison estimée sous 15 jours ouvrés maximum.', 'es' => 'Entrega estimada en hasta 15 días hábiles.'],
                'rate' => 30.00, 'free_over' => null, 'min_d' => 10, 'max_d' => 15, 'sort' => 30,
            ],
        ];

        foreach ($methods as $method) {
            ShippingMethod::create([
                'zone_id'                 => $zone->id,
                'name'                    => $method['name'],
                'description'             => $method['desc'],
                'flat_rate'               => $method['rate'],
                'free_shipping_threshold' => $method['free_over'],
                'estimated_days_min'      => $method['min_d'],
                'estimated_days_max'      => $method['max_d'],
                'is_active'               => true,
                'sort_order'              => $method['sort'],
            ]);
        }

        $this->command?->info(sprintf(
            '  ✓ Seeded %d zone, %d methods',
            ShippingZone::count(),
            ShippingMethod::count()
        ));
    }
}
