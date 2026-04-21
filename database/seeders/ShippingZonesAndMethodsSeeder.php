<?php

namespace Database\Seeders;

use App\Models\ShippingCountry;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds a complete pan-European shipping matrix.
 *
 *   Zone 1 — EU Core (DE-based hub, next-day reach)
 *   Zone 2 — EU Western
 *   Zone 3 — EU Nordic + Baltic
 *   Zone 4 — EU Southern + Islands
 *   Zone 5 — UK & Switzerland (non-EU VAT)
 *   Zone 6 — Extended Europe (Balkans, microstates, Ukraine, Moldova, etc.)
 *
 * Every European country is covered. Idempotent: truncates and re-seeds.
 */
class ShippingZonesAndMethodsSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        ShippingMethod::truncate();
        ShippingCountry::truncate();
        ShippingZone::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $zones = [
            // ══════════════════════════════════════════════════════════════════
            // ZONE 1 — EU CORE (hub: Germany)
            // ══════════════════════════════════════════════════════════════════
            [
                'name' => 'EU Core',
                'sort_order' => 10,
                'countries' => [
                    'DE' => 'Germany',
                    'AT' => 'Austria',
                    'NL' => 'Netherlands',
                    'BE' => 'Belgium',
                    'LU' => 'Luxembourg',
                    'CZ' => 'Czech Republic',
                    'PL' => 'Poland',
                    'DK' => 'Denmark',
                ],
                'methods' => [
                    [
                        'name'    => ['en' => 'Standard', 'de' => 'Standard', 'lt' => 'Standartas', 'fr' => 'Standard', 'es' => 'Estándar'],
                        'desc'    => ['en' => 'Tracked parcel via DHL/DPD. Free over €120.', 'de' => 'Sendungsverfolgung per DHL/DPD. Frei ab €120.', 'lt' => 'Sekimo siunta per DHL/DPD. Nemokamai virš €120.', 'fr' => 'Colis suivi via DHL/DPD. Gratuit au-delà de €120.', 'es' => 'Envío con seguimiento vía DHL/DPD. Gratis desde €120.'],
                        'rate' => 6.90, 'free_over' => 120, 'min_d' => 2, 'max_d' => 3, 'sort' => 10,
                    ],
                    [
                        'name'    => ['en' => 'Express', 'de' => 'Express', 'lt' => 'Express', 'fr' => 'Express', 'es' => 'Express'],
                        'desc'    => ['en' => 'Priority overnight where possible. Signature required.', 'de' => 'Priorität über Nacht. Unterschrift erforderlich.', 'lt' => 'Prioritetas per naktį. Reikalingas parašas.', 'fr' => 'Priorité 24h. Signature requise.', 'es' => 'Prioridad 24h. Firma requerida.'],
                        'rate' => 14.90, 'free_over' => 300, 'min_d' => 1, 'max_d' => 2, 'sort' => 20,
                    ],
                    [
                        'name'    => ['en' => 'Next-Day', 'de' => 'Sofort-Lieferung', 'lt' => 'Kitą dieną', 'fr' => 'Lendemain', 'es' => 'Día siguiente'],
                        'desc'    => ['en' => 'Morning cut-off 12:00 CET. Delivered next business day.', 'de' => 'Annahmeschluss 12:00 MEZ. Lieferung am nächsten Werktag.', 'lt' => 'Priėmimas iki 12:00 CET. Pristatymas kitą darbo dieną.', 'fr' => 'Cut-off 12:00 CET. Livré le lendemain ouvré.', 'es' => 'Corte 12:00 CET. Entrega al siguiente día hábil.'],
                        'rate' => 22.90, 'free_over' => null, 'min_d' => 1, 'max_d' => 1, 'sort' => 30,
                    ],
                ],
            ],

            // ══════════════════════════════════════════════════════════════════
            // ZONE 2 — EU WESTERN
            // ══════════════════════════════════════════════════════════════════
            [
                'name' => 'EU Western',
                'sort_order' => 20,
                'countries' => [
                    'FR' => 'France',
                    'IT' => 'Italy',
                    'ES' => 'Spain',
                    'PT' => 'Portugal',
                    'IE' => 'Ireland',
                ],
                'methods' => [
                    [
                        'name'    => ['en' => 'Standard', 'de' => 'Standard', 'lt' => 'Standartas', 'fr' => 'Standard', 'es' => 'Estándar'],
                        'desc'    => ['en' => 'Tracked EU courier. Free over €150.', 'de' => 'EU-Kurier mit Tracking. Frei ab €150.', 'lt' => 'ES kurjeris su sekimu. Nemokamai virš €150.', 'fr' => 'Transporteur UE suivi. Gratuit au-delà de €150.', 'es' => 'Mensajería UE con seguimiento. Gratis desde €150.'],
                        'rate' => 9.90, 'free_over' => 150, 'min_d' => 3, 'max_d' => 5, 'sort' => 10,
                    ],
                    [
                        'name'    => ['en' => 'Express', 'de' => 'Express', 'lt' => 'Express', 'fr' => 'Express', 'es' => 'Express'],
                        'desc'    => ['en' => 'Priority courier with signature.', 'de' => 'Priorität-Kurier mit Unterschrift.', 'lt' => 'Prioritetinis kurjeris su parašu.', 'fr' => 'Transporteur prioritaire avec signature.', 'es' => 'Mensajería prioritaria con firma.'],
                        'rate' => 19.90, 'free_over' => 350, 'min_d' => 2, 'max_d' => 3, 'sort' => 20,
                    ],
                ],
            ],

            // ══════════════════════════════════════════════════════════════════
            // ZONE 3 — EU NORDIC + BALTIC
            // ══════════════════════════════════════════════════════════════════
            [
                'name' => 'EU Nordic & Baltic',
                'sort_order' => 30,
                'countries' => [
                    'SE' => 'Sweden',
                    'FI' => 'Finland',
                    'EE' => 'Estonia',
                    'LV' => 'Latvia',
                    'LT' => 'Lithuania',
                ],
                'methods' => [
                    [
                        'name'    => ['en' => 'Standard', 'de' => 'Standard', 'lt' => 'Standartas', 'fr' => 'Standard', 'es' => 'Estándar'],
                        'desc'    => ['en' => 'Tracked parcel. Free over €180.', 'de' => 'Sendungsverfolgung. Frei ab €180.', 'lt' => 'Sekimo siunta. Nemokamai virš €180.', 'fr' => 'Colis suivi. Gratuit au-delà de €180.', 'es' => 'Envío con seguimiento. Gratis desde €180.'],
                        'rate' => 11.90, 'free_over' => 180, 'min_d' => 3, 'max_d' => 5, 'sort' => 10,
                    ],
                    [
                        'name'    => ['en' => 'Express', 'de' => 'Express', 'lt' => 'Express', 'fr' => 'Express', 'es' => 'Express'],
                        'desc'    => ['en' => 'Priority air-freight courier.', 'de' => 'Priorität-Luftfracht-Kurier.', 'lt' => 'Prioritetinis oro frachtas.', 'fr' => 'Transporteur fret aérien prioritaire.', 'es' => 'Mensajería prioritaria aérea.'],
                        'rate' => 22.90, 'free_over' => 400, 'min_d' => 2, 'max_d' => 3, 'sort' => 20,
                    ],
                ],
            ],

            // ══════════════════════════════════════════════════════════════════
            // ZONE 4 — EU SOUTHERN + ISLANDS
            // ══════════════════════════════════════════════════════════════════
            [
                'name' => 'EU Southern & Islands',
                'sort_order' => 40,
                'countries' => [
                    'HU' => 'Hungary',
                    'SK' => 'Slovakia',
                    'SI' => 'Slovenia',
                    'HR' => 'Croatia',
                    'RO' => 'Romania',
                    'BG' => 'Bulgaria',
                    'GR' => 'Greece',
                    'CY' => 'Cyprus',
                    'MT' => 'Malta',
                ],
                'methods' => [
                    [
                        'name'    => ['en' => 'Standard', 'de' => 'Standard', 'lt' => 'Standartas', 'fr' => 'Standard', 'es' => 'Estándar'],
                        'desc'    => ['en' => 'Tracked courier. Free over €180.', 'de' => 'Tracking-Kurier. Frei ab €180.', 'lt' => 'Sekimo kurjeris. Nemokamai virš €180.', 'fr' => 'Transporteur suivi. Gratuit au-delà de €180.', 'es' => 'Mensajería con seguimiento. Gratis desde €180.'],
                        'rate' => 12.90, 'free_over' => 180, 'min_d' => 4, 'max_d' => 6, 'sort' => 10,
                    ],
                    [
                        'name'    => ['en' => 'Express', 'de' => 'Express', 'lt' => 'Express', 'fr' => 'Express', 'es' => 'Express'],
                        'desc'    => ['en' => 'Priority courier with signature.', 'de' => 'Priorität-Kurier mit Unterschrift.', 'lt' => 'Prioritetinis kurjeris su parašu.', 'fr' => 'Transporteur prioritaire avec signature.', 'es' => 'Mensajería prioritaria con firma.'],
                        'rate' => 22.90, 'free_over' => 400, 'min_d' => 2, 'max_d' => 4, 'sort' => 20,
                    ],
                ],
            ],

            // ══════════════════════════════════════════════════════════════════
            // ZONE 5 — UK & SWITZERLAND (non-EU VAT, customs-cleared)
            // ══════════════════════════════════════════════════════════════════
            [
                'name' => 'UK & Switzerland',
                'sort_order' => 50,
                'countries' => [
                    'GB' => 'United Kingdom',
                    'CH' => 'Switzerland',
                    'LI' => 'Liechtenstein',
                    'NO' => 'Norway',
                    'IS' => 'Iceland',
                ],
                'methods' => [
                    [
                        'name'    => ['en' => 'Standard (DDP)', 'de' => 'Standard (verzollt)', 'lt' => 'Standartas (DDP)', 'fr' => 'Standard (DDP)', 'es' => 'Estándar (DDP)'],
                        'desc'    => ['en' => 'Duties & VAT pre-paid. Free over €250.', 'de' => 'Zoll und MwSt. vorausbezahlt. Frei ab €250.', 'lt' => 'Muitai ir PVM apmokėti. Nemokamai virš €250.', 'fr' => 'Droits et TVA pré-payés. Gratuit au-delà de €250.', 'es' => 'Aranceles e IVA pre-pagados. Gratis desde €250.'],
                        'rate' => 14.90, 'free_over' => 250, 'min_d' => 4, 'max_d' => 6, 'sort' => 10,
                    ],
                    [
                        'name'    => ['en' => 'Express (DDP)', 'de' => 'Express (verzollt)', 'lt' => 'Express (DDP)', 'fr' => 'Express (DDP)', 'es' => 'Express (DDP)'],
                        'desc'    => ['en' => 'Priority customs-cleared courier.', 'de' => 'Priorität-Kurier mit Zollabfertigung.', 'lt' => 'Prioritetinis muitinės kurjeris.', 'fr' => 'Transporteur prioritaire dédouané.', 'es' => 'Mensajería prioritaria despachada.'],
                        'rate' => 24.90, 'free_over' => 500, 'min_d' => 2, 'max_d' => 4, 'sort' => 20,
                    ],
                ],
            ],

            // ══════════════════════════════════════════════════════════════════
            // ZONE 6 — EXTENDED EUROPE (Balkans, Ukraine, Moldova, microstates)
            // ══════════════════════════════════════════════════════════════════
            [
                'name' => 'Extended Europe',
                'sort_order' => 60,
                'countries' => [
                    // Balkans (non-EU)
                    'RS' => 'Serbia',
                    'BA' => 'Bosnia and Herzegovina',
                    'MK' => 'North Macedonia',
                    'AL' => 'Albania',
                    'ME' => 'Montenegro',
                    'XK' => 'Kosovo',
                    // Eastern Europe (non-EU)
                    'UA' => 'Ukraine',
                    'MD' => 'Moldova',
                    'BY' => 'Belarus',
                    // Microstates
                    'AD' => 'Andorra',
                    'MC' => 'Monaco',
                    'SM' => 'San Marino',
                    'VA' => 'Vatican City',
                    'GI' => 'Gibraltar',
                    'FO' => 'Faroe Islands',
                    'IM' => 'Isle of Man',
                    'JE' => 'Jersey',
                    'GG' => 'Guernsey',
                    // Transcontinental (European-facing)
                    'TR' => 'Türkiye',
                ],
                'methods' => [
                    [
                        'name'    => ['en' => 'Standard', 'de' => 'Standard', 'lt' => 'Standartas', 'fr' => 'Standard', 'es' => 'Estándar'],
                        'desc'    => ['en' => 'Tracked international courier. Free over €300.', 'de' => 'Internationaler Kurier mit Tracking. Frei ab €300.', 'lt' => 'Tarptautinis sekimo kurjeris. Nemokamai virš €300.', 'fr' => 'Transporteur international suivi. Gratuit au-delà de €300.', 'es' => 'Mensajería internacional seguida. Gratis desde €300.'],
                        'rate' => 19.90, 'free_over' => 300, 'min_d' => 5, 'max_d' => 10, 'sort' => 10,
                    ],
                    [
                        'name'    => ['en' => 'Express', 'de' => 'Express', 'lt' => 'Express', 'fr' => 'Express', 'es' => 'Express'],
                        'desc'    => ['en' => 'Priority international air courier.', 'de' => 'Prioritäts-Luftkurier international.', 'lt' => 'Prioritetinis oro kurjeris.', 'fr' => 'Transporteur aérien international prioritaire.', 'es' => 'Mensajería aérea internacional prioritaria.'],
                        'rate' => 34.90, 'free_over' => 600, 'min_d' => 3, 'max_d' => 6, 'sort' => 20,
                    ],
                ],
            ],
        ];

        foreach ($zones as $zoneData) {
            $zone = ShippingZone::create([
                'name'       => $zoneData['name'],
                'is_active'  => true,
                'sort_order' => $zoneData['sort_order'],
            ]);

            foreach ($zoneData['countries'] as $code => $name) {
                ShippingCountry::create([
                    'zone_id'      => $zone->id,
                    'country_code' => $code,
                    'country_name' => $name,
                ]);
            }

            foreach ($zoneData['methods'] as $method) {
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
        }

        $this->command?->info(sprintf(
            '  ✓ Seeded %d zones, %d countries, %d methods',
            ShippingZone::count(),
            ShippingCountry::count(),
            ShippingMethod::count()
        ));
    }
}
