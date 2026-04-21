<?php

namespace App\Services;

/**
 * OEM number normalizer — strips all non-alphanumeric characters and uppercases.
 *
 * ALWAYS normalize before saving or searching OEM numbers.
 * NEVER search on oem_number column directly — always use normalized_oem.
 */
class OemNormalizerService
{
    /**
     * Normalize an OEM number: strip everything except A-Z and 0-9, uppercase.
     *
     * Examples:
     *   "06L-906-036-L" → "06L906036L"
     *   "06l906036l"    → "06L906036L"
     *   "BMW 11 22 7 835 903" → "BMW11227835903"
     */
    public function normalize(string $oem): string
    {
        return strtoupper(preg_replace('/[^A-Z0-9]/i', '', $oem));
    }

    /**
     * Check if a string is already normalized.
     */
    public function isNormalized(string $oem): bool
    {
        return $oem === $this->normalize($oem);
    }
}
