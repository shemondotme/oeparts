<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LocaleFormattingTest extends TestCase
{
    #[Test]
    public function format_price_uses_locale_aware_formatting(): void
    {
        // Test English formatting
        app()->setLocale('en');
        $en = format_price(1234.56);
        $this->assertStringContainsString('1,234.56', $en);
        $this->assertStringContainsString('€', $en);

        // Test German formatting
        app()->setLocale('de');
        $de = format_price(1234.56);
        $this->assertStringContainsString('1.234,56', $de);
        $this->assertStringContainsString('€', $de);

        // Test Lithuanian formatting (uses non-breaking space)
        app()->setLocale('lt');
        $lt = format_price(1234.56);
        $this->assertStringContainsString('234,56', $lt);
        $this->assertStringContainsString('€', $lt);

        // Reset locale
        app()->setLocale('en');
    }

    #[Test]
    public function format_price_handles_different_currencies(): void
    {
        app()->setLocale('en');
        
        $eur = format_price(100, 'EUR');
        $this->assertStringContainsString('€', $eur);

        $usd = format_price(100, 'USD');
        $this->assertStringContainsString('$', $usd);
    }

    #[Test]
    public function format_date_uses_locale_aware_formatting(): void
    {
        $date = '2025-03-14';

        // Test English formatting
        app()->setLocale('en');
        $en = format_date($date);
        $this->assertNotEmpty($en);
        $this->assertStringContainsString('2025', $en);

        // Test German formatting
        app()->setLocale('de');
        $de = format_date($date);
        $this->assertNotEmpty($de);
        $this->assertStringContainsString('2025', $de);

        // Reset locale
        app()->setLocale('en');
    }

    #[Test]
    public function format_datetime_includes_time(): void
    {
        $datetime = '2025-03-14 14:30:00';

        app()->setLocale('en');
        $formatted = format_datetime($datetime);
        
        $this->assertNotEmpty($formatted);
        $this->assertStringContainsString('2025', $formatted);
    }

    #[Test]
    public function format_money_is_alias_for_format_price(): void
    {
        app()->setLocale('en');
        
        $price = format_price(100.50);
        $money = format_money(100.50);
        
        $this->assertEquals($price, $money);
    }

    #[Test]
    public function format_date_returns_empty_string_for_null(): void
    {
        $this->assertEquals('', format_date(null));
    }

    #[Test]
    public function format_datetime_returns_empty_string_for_null(): void
    {
        $this->assertEquals('', format_datetime(null));
    }
}
