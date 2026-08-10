<?php

namespace Tests\Feature;

use App\Enums\SectionStatus;
use App\Models\Admin;
use App\Models\Faq;
use App\Models\Section;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CmsLowFixesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget('sections.homepage');
        Cache::forget('sections.landing');
        Cache::forget('settings.performance');
    }

    // ── cms-17: section preview requires 'edit sections', not just any admin ──

    #[Test]
    public function section_preview_is_rejected_for_an_admin_without_edit_sections_permission(): void
    {
        $section = Section::create([
            'type' => 'faqs', 'location' => 'homepage', 'title' => 'FAQs',
            'content' => [], 'is_active' => true, 'status' => SectionStatus::Published, 'sort_order' => 10,
        ]);

        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');

        $response = $this->postJson(route('admin.cms.sections.preview', ['section' => $section->id]), [
            'content' => ['en' => ['headline' => 'x', 'description' => 'y']],
            'lang' => 'en',
        ]);

        $response->assertStatus(403);
    }

    #[Test]
    public function section_preview_succeeds_for_an_admin_with_edit_sections_permission(): void
    {
        $section = Section::create([
            'type' => 'faqs', 'location' => 'homepage', 'title' => 'FAQs',
            'content' => [], 'is_active' => true, 'status' => SectionStatus::Published, 'sort_order' => 10,
        ]);

        $admin = Admin::factory()->create();
        \Spatie\Permission\Models\Permission::findOrCreate('edit sections', 'admin');
        $admin->givePermissionTo('edit sections');
        $this->actingAs($admin, 'admin');

        $response = $this->postJson(route('admin.cms.sections.preview', ['section' => $section->id]), [
            'content' => ['en' => ['headline' => 'x', 'description' => 'y']],
            'lang' => 'en',
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
    }

    // ── cms-18: the homepage FAQ section actually groups by category ──

    #[Test]
    public function homepage_faq_section_groups_entries_by_category(): void
    {
        Section::create([
            'type' => 'faqs', 'location' => 'homepage', 'title' => 'FAQs',
            'content' => ['eyebrow' => ['en' => 'Help'], 'headline' => ['en' => 'Questions'], 'subheadline' => ['en' => '']],
            'is_active' => true, 'status' => SectionStatus::Published, 'sort_order' => 10,
        ]);

        Faq::create(['category' => 'shipping', 'question' => ['en' => 'How fast is shipping?'], 'answer' => ['en' => 'Fast.'], 'sort_order' => 1, 'is_active' => true]);
        Faq::create(['category' => 'returns', 'question' => ['en' => 'Can I return a part?'], 'answer' => ['en' => 'Yes.'], 'sort_order' => 2, 'is_active' => true]);

        $response = $this->get('/en/');

        $response->assertStatus(200);
        $response->assertSeeInOrder(['Shipping', 'How fast is shipping?', 'Returns', 'Can I return a part?']);
    }
}
