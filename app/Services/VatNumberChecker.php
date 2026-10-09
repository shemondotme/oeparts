<?php

namespace App\Services;

/**
 * A human-readable VIES check of a VAT number typed on an admin form
 * ("LT100001919017", "DE 123 456 789", or just digits plus a country).
 * Only EU numbers can be checked; the answer is advice for the admin, never
 * a gate (VIES is often down and some valid numbers are not listed).
 */
class VatNumberChecker
{
    /** VAT prefixes that differ from the ISO country code. */
    private const PREFIX_TO_ISO = ['EL' => 'GR'];

    public function __construct(private readonly ViesService $vies) {}

    /**
     * @return array{status: string, message: string, name: ?string, address: ?string}
     *                                                                                 status: empty | not_eu | valid | invalid | unavailable
     */
    public function check(?string $vatNumber, ?string $fallbackCountry = null): array
    {
        $vat = strtoupper((string) preg_replace('/[\s.\-]+/', '', (string) $vatNumber));

        if ($vat === '') {
            return $this->result('empty', 'Enter a VAT number first.');
        }

        $country = null;
        if (preg_match('/^([A-Z]{2})(.+)$/', $vat, $m)) {
            $country = self::PREFIX_TO_ISO[$m[1]] ?? $m[1];
            $number = $m[2];
        } else {
            $country = strtoupper((string) $fallbackCountry);
            $number = $vat;
        }

        if ($country === '' || ! $this->vies->isEuCountry($country)) {
            return $this->result('not_eu', 'Only EU VAT numbers can be checked online (VIES). For other countries, verify it with the client or their tax authority.');
        }

        $r = $this->vies->validate($country, $number);

        if ($r->isValid()) {
            $detail = trim(implode(' — ', array_filter([$r->name, $r->address])));

            return $this->result('valid', 'Valid in VIES'.($detail !== '' ? ': '.$detail : '.'), $r->name, $r->address);
        }

        if ($r->isUnavailable()) {
            return $this->result('unavailable', $r->isRateLimited()
                ? 'Too many checks right now — try again in a minute.'
                : 'VIES could not be reached or did not answer. Try again later; the invoice can still be saved.');
        }

        return $this->result('invalid', 'VIES does not know this number. Check it with the client before invoicing without VAT.');
    }

    /** @return array{status: string, message: string, name: ?string, address: ?string} */
    private function result(string $status, string $message, ?string $name = null, ?string $address = null): array
    {
        return ['status' => $status, 'message' => $message, 'name' => $name, 'address' => $address];
    }
}
