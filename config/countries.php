<?php

use App\Services\ViesService;

/**
 * ISO 3166-1 alpha-2 => English label for admin selects (manufacturer country of origin, filters, etc.).
 * Starts from ViesService EU+ list, then merges common automotive / supplier origins.
 */
$list = ViesService::getEuCountries();

$extra = [
    'US' => 'United States',
    'JP' => 'Japan',
    'CN' => 'China',
    'KR' => 'South Korea',
    'CA' => 'Canada',
    'AU' => 'Australia',
    'MX' => 'Mexico',
    'IN' => 'India',
    'TW' => 'Taiwan',
    'MY' => 'Malaysia',
    'TH' => 'Thailand',
    'VN' => 'Vietnam',
    'ID' => 'Indonesia',
    'TR' => 'Türkiye',
    'BR' => 'Brazil',
    'ZA' => 'South Africa',
    'AE' => 'United Arab Emirates',
    'NZ' => 'New Zealand',
    'SG' => 'Singapore',
    'PH' => 'Philippines',
];

foreach ($extra as $code => $name) {
    $list[$code] = $name;
}

asort($list, SORT_NATURAL | SORT_FLAG_CASE);

return $list;
