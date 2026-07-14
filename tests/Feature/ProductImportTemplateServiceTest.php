<?php

namespace Tests\Feature;

use App\Models\Language;
use App\Services\Imports\ProductImportTemplateService;
use App\Services\ProductImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductImportTemplateServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): ProductImportTemplateService
    {
        return app(ProductImportTemplateService::class);
    }

    private function headerRow(string $csv): array
    {
        $lines = explode("\n", trim($csv));

        return str_getcsv($lines[0]);
    }

    #[Test]
    public function the_quick_template_has_exactly_the_required_columns(): void
    {
        $headers = $this->headerRow($this->service()->quickCsv());

        $this->assertSame(ProductImportService::REQUIRED_COLUMNS, $headers);
    }

    #[Test]
    public function the_quick_template_includes_one_valid_example_row(): void
    {
        $lines = explode("\n", trim($this->service()->quickCsv()));
        $this->assertCount(2, $lines, 'header + one example row');

        $example = array_combine(ProductImportService::REQUIRED_COLUMNS, str_getcsv($lines[1]));
        $this->assertNotSame('', $example['oem_number']);
        $this->assertNotSame('', $example['manufacturer_slug']);
        $this->assertNotSame('', $example['condition_slug']);
        $this->assertTrue(is_numeric($example['price']));
        $this->assertContains($example['is_in_stock'], ['0', '1']);
    }

    #[Test]
    public function the_full_template_includes_a_name_and_description_pair_per_active_language(): void
    {
        $this->seed([\Database\Seeders\LanguagesSeeder::class]);

        $headers = $this->headerRow($this->service()->fullCsv());

        foreach (['en', 'de', 'lt', 'fr', 'es'] as $code) {
            $this->assertContains("name_{$code}", $headers);
            $this->assertContains("description_{$code}", $headers);
        }

        foreach (ProductImportService::OPTIONAL_COLUMNS as $col) {
            $this->assertContains($col, $headers);
        }

        foreach (ProductImportService::REQUIRED_COLUMNS as $col) {
            $this->assertContains($col, $headers);
        }
    }

    #[Test]
    public function the_full_template_excludes_inactive_languages(): void
    {
        $this->seed([\Database\Seeders\LanguagesSeeder::class]);
        Language::where('code', 'es')->update(['is_active' => false]);

        $headers = $this->headerRow($this->service()->fullCsv());

        $this->assertContains('name_de', $headers);
        $this->assertNotContains('name_es', $headers);
        $this->assertNotContains('description_es', $headers);
    }

    #[Test]
    public function the_full_template_falls_back_to_the_hardcoded_language_list_when_none_are_seeded(): void
    {
        // No LanguagesSeeder call — the languages table is empty.
        $headers = $this->headerRow($this->service()->fullCsv());

        foreach (ProductImportService::LANGUAGES as $code) {
            $this->assertContains("name_{$code}", $headers);
        }
    }
}
