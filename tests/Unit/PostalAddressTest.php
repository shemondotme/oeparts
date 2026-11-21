<?php

namespace Tests\Unit;

use App\Services\SeoService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PostalAddressTest extends TestCase
{
    /** @return array<string, array{0: string, 1: array<string, string>|string|null}> */
    public static function addresses(): array
    {
        return [
            'street, city, country' => ['Laisvės alėja 110, Kaunas, Lithuania', [
                '@type' => 'PostalAddress', 'streetAddress' => 'Laisvės alėja 110', 'addressLocality' => 'Kaunas', 'addressCountry' => 'Lithuania',
            ]],
            'street, postcode and city, country' => ['Musterstrasse 1, 10115 Berlin, Germany', [
                '@type' => 'PostalAddress', 'streetAddress' => 'Musterstrasse 1', 'postalCode' => '10115', 'addressLocality' => 'Berlin', 'addressCountry' => 'Germany',
            ]],
            'street and city only' => ['Rue 5, Paris', [
                '@type' => 'PostalAddress', 'streetAddress' => 'Rue 5', 'addressLocality' => 'Paris',
            ]],
            'postcode with a space' => ['Gedimino pr. 1, LT-01103 Vilnius, Lithuania', [
                '@type' => 'PostalAddress', 'streetAddress' => 'Gedimino pr. 1', 'addressLocality' => 'LT-01103 Vilnius', 'addressCountry' => 'Lithuania',
            ]],
            'one word stays a plain string' => ['Vilnius', 'Vilnius'],
            'too many parts stay a plain string' => ['A, B, C, D, E', 'A, B, C, D, E'],
            'empty is left out' => ['   ', null],
            'stray commas are ignored' => [', Main St 1 , Riga ,', [
                '@type' => 'PostalAddress', 'streetAddress' => 'Main St 1', 'addressLocality' => 'Riga',
            ]],
        ];
    }

    /**
     * @param  array<string, string>|string|null  $expected
     */
    #[Test]
    #[DataProvider('addresses')]
    public function it_splits_the_one_line_address_without_inventing_anything(string $raw, array|string|null $expected): void
    {
        $this->assertSame($expected, SeoService::postalAddress($raw));
    }
}
