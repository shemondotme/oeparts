<?php

namespace Database\Seeders;

use App\Models\TaxRate;
use Illuminate\Database\Seeder;

/**
 * Starting-point standard VAT/GST rates for every country already recognized
 * site-wide (ViesService::EU_COUNTRY_NAMES — the same list used for the
 * manufacturer country dropdown and shipping zone countries). Kept idempotent
 * via updateOrCreate (keyed by country_code) so re-seeding never clobbers a
 * rate an admin has already corrected here.
 *
 * IMPORTANT: these are seeded defaults only, not a live feed. Standard VAT
 * rates change (several EU states changed theirs in 2024/2025) — confirm
 * every rate against an official source (your accountant, or
 * https://ec.europa.eu/taxation_customs/vies/) before relying on it for real
 * invoicing. Country-based VAT stays OFF (tax.country_based_vat_enabled)
 * until an operator explicitly enables it on the Tax Settings page, so
 * seeding this table never silently changes what customers are charged.
 */
class TaxRatesSeeder extends Seeder
{
    public function run(): void
    {
        $rates = [
            // EU member states — standard VAT rate.
            ['AT', 'Austria', 20.00],
            ['BE', 'Belgium', 21.00],
            ['BG', 'Bulgaria', 20.00],
            ['HR', 'Croatia', 25.00],
            ['CY', 'Cyprus', 19.00],
            ['CZ', 'Czech Republic', 21.00],
            ['DK', 'Denmark', 25.00],
            ['EE', 'Estonia', 22.00],
            ['FI', 'Finland', 25.50],
            ['FR', 'France', 20.00],
            ['DE', 'Germany', 19.00],
            ['GR', 'Greece', 24.00],
            ['HU', 'Hungary', 27.00],
            ['IE', 'Ireland', 23.00],
            ['IT', 'Italy', 22.00],
            ['LV', 'Latvia', 21.00],
            ['LT', 'Lithuania', 21.00],
            ['LU', 'Luxembourg', 17.00],
            ['MT', 'Malta', 18.00],
            ['NL', 'Netherlands', 21.00],
            ['PL', 'Poland', 23.00],
            ['PT', 'Portugal', 23.00],
            ['RO', 'Romania', 19.00],
            ['SK', 'Slovakia', 23.00],
            ['SI', 'Slovenia', 22.00],
            ['ES', 'Spain', 21.00],
            ['SE', 'Sweden', 25.00],
            // EEA / other European — own national standard VAT/GST rate, not EU VAT.
            ['NO', 'Norway', 25.00],
            ['CH', 'Switzerland', 8.10],
            ['GB', 'United Kingdom', 20.00],
            ['IS', 'Iceland', 24.00],
            ['LI', 'Liechtenstein', 8.10],
        ];

        // firstOrCreate (not updateOrCreate): only inserts a country that isn't
        // in the table yet — never overwrites a rate an admin has since
        // corrected on the Tax Rates page. Safe to run unattended on every
        // future update (e.g. this file gaining a 33rd country later).
        foreach ($rates as [$code, $name, $rate]) {
            TaxRate::firstOrCreate(
                ['country_code' => $code],
                ['country_name' => $name, 'rate' => $rate, 'is_active' => true],
            );
        }
    }
}
