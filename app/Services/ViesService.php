<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * ViesService — validate EU VAT numbers via the VIES SOAP service.
 *
 * VIES endpoint: https://ec.europa.eu/taxation_customs/vies/services/checkVatService
 *
 * Usage:
 *   $result = app(ViesService::class)->validate('LT', '100001919314');
 *   if ($result->valid) { ... }
 *
 * Falls back gracefully when VIES is unavailable (returns null for valid).
 */
class ViesService
{
    private const WSDL = 'https://ec.europa.eu/taxation_customs/vies/services/checkVatService?wsdl';

    private const EU_COUNTRIES = [
        'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR',
        'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL',
        'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE',
    ];

    /**
     * EU country code => English display name. Also includes a few
     * commonly-shipped European countries outside the EU for UX parity
     * with the rest of the checkout/country selectors.
     */
    private const EU_COUNTRY_NAMES = [
        'AT' => 'Austria',
        'BE' => 'Belgium',
        'BG' => 'Bulgaria',
        'HR' => 'Croatia',
        'CY' => 'Cyprus',
        'CZ' => 'Czech Republic',
        'DK' => 'Denmark',
        'EE' => 'Estonia',
        'FI' => 'Finland',
        'FR' => 'France',
        'DE' => 'Germany',
        'GR' => 'Greece',
        'HU' => 'Hungary',
        'IE' => 'Ireland',
        'IT' => 'Italy',
        'LV' => 'Latvia',
        'LT' => 'Lithuania',
        'LU' => 'Luxembourg',
        'MT' => 'Malta',
        'NL' => 'Netherlands',
        'PL' => 'Poland',
        'PT' => 'Portugal',
        'RO' => 'Romania',
        'SK' => 'Slovakia',
        'SI' => 'Slovenia',
        'ES' => 'Spain',
        'SE' => 'Sweden',
        'NO' => 'Norway',
        'CH' => 'Switzerland',
        'GB' => 'United Kingdom',
        'IS' => 'Iceland',
        'LI' => 'Liechtenstein',
    ];

    /**
     * Return EU (+ near-Europe) countries keyed by ISO-3166-1 alpha-2 code,
     * sorted alphabetically by English display name.
     *
     * @return array<string,string>  ['DE' => 'Germany', ...]
     */
    public static function getEuCountries(): array
    {
        $list = self::EU_COUNTRY_NAMES;
        asort($list, SORT_NATURAL | SORT_FLAG_CASE);
        return $list;
    }

    /**
     * Validate a VAT number via VIES.
     *
     * @param  string  $countryCode  2-letter ISO country code (e.g. 'DE')
     * @param  string  $vatNumber    VAT number without country prefix (e.g. '123456789')
     * @return ViesResult
     */
    public function validate(string $countryCode, string $vatNumber): ViesResult
    {
        $countryCode = strtoupper(trim($countryCode));
        $vatNumber   = $this->normalizeVatNumber($countryCode, trim($vatNumber));

        if (! $this->isEuCountry($countryCode)) {
            return new ViesResult(valid: false, reason: 'not_eu', countryCode: $countryCode, vatNumber: $vatNumber);
        }

        try {
            $client = new \SoapClient(self::WSDL, [
                'connection_timeout' => 10,
                'cache_wsdl'         => WSDL_CACHE_DISK,
            ]);

            $response = $client->checkVat([
                'countryCode' => $countryCode,
                'vatNumber'   => $vatNumber,
            ]);

            $valid = (bool) ($response->valid ?? false);

            return new ViesResult(
                valid:       $valid,
                reason:      $valid ? null : 'invalid',
                countryCode: $countryCode,
                vatNumber:   $vatNumber,
                name:        $response->name ?? null,
                address:     $response->address ?? null,
            );
        } catch (\SoapFault $e) {
            Log::warning('VIES SOAP fault', [
                'country' => $countryCode,
                'vat'     => $vatNumber,
                'fault'   => $e->getMessage(),
            ]);

            // SERVICE_UNAVAILABLE or MS_UNAVAILABLE — treat as unverifiable
            return new ViesResult(valid: null, reason: 'service_unavailable', countryCode: $countryCode, vatNumber: $vatNumber);
        } catch (\Exception $e) {
            Log::error('VIES validation error', [
                'country' => $countryCode,
                'vat'     => $vatNumber,
                'error'   => $e->getMessage(),
            ]);

            return new ViesResult(valid: null, reason: 'error', countryCode: $countryCode, vatNumber: $vatNumber);
        }
    }

    /**
     * Check if the given country code is an EU member state.
     */
    public function isEuCountry(string $countryCode): bool
    {
        return in_array(strtoupper($countryCode), self::EU_COUNTRIES, true);
    }

    /**
     * Strip the country prefix from a VAT number if the customer included it.
     * E.g. "DE123456789" → "123456789" when countryCode='DE'
     */
    private function normalizeVatNumber(string $countryCode, string $vatNumber): string
    {
        $vatNumber = preg_replace('/[\s.\-]/', '', $vatNumber);

        if (str_starts_with(strtoupper($vatNumber), $countryCode)) {
            $vatNumber = substr($vatNumber, strlen($countryCode));
        }

        return strtoupper($vatNumber);
    }
}

/**
 * Value object returned by ViesService::validate().
 *
 * @property bool|null   $valid        true=valid, false=invalid, null=unverifiable
 * @property string|null $reason       null | 'invalid' | 'not_eu' | 'service_unavailable' | 'error'
 * @property string      $countryCode
 * @property string      $vatNumber
 * @property string|null $name         Business name from VIES (if available)
 * @property string|null $address      Business address from VIES (if available)
 */
readonly class ViesResult
{
    public function __construct(
        public bool|null $valid,
        public ?string   $reason,
        public string    $countryCode,
        public string    $vatNumber,
        public ?string   $name    = null,
        public ?string   $address = null,
    ) {}

    public function isValid(): bool
    {
        return $this->valid === true;
    }

    public function isUnavailable(): bool
    {
        return $this->valid === null;
    }
}
