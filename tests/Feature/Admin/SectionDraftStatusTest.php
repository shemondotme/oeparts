<?php

namespace Tests\Feature\Admin;

use App\Enums\SectionStatus;
use App\Models\Section;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SectionDraftStatusTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function new_section_can_be_created_as_draft(): void
    {
        $section = Section::create([
            'type' => 'hero',
            'location' => 'homepage',
            'title' => ['en' => 'Draft Hero'],
            'content' => ['en' => 'This is draft content'],
            'status' => SectionStatus::Draft,
            'is_active' => true,
        ]);

        $this->assertEquals(SectionStatus::Draft, $section->status);
        $this->assertFalse($section->isVisible());
    }

    #[Test]
    public function section_can_be_published_immediately(): void
    {
        $section = Section::create([
            'type' => 'banner',
            'location' => 'homepage',
            'title' => ['en' => 'Banner'],
            'content' => ['en' => 'Content'],
            'status' => SectionStatus::Published,
            'is_active' => true,
        ]);

        $this->assertTrue($section->isVisible());
    }

    #[Test]
    public function section_can_be_scheduled(): void
    {
        $section = Section::create([
            'type' => 'cta',
            'location' => 'homepage',
            'title' => ['en' => 'Scheduled CTA'],
            'content' => ['en' => 'Content'],
            'status' => SectionStatus::Scheduled,
            'publish_at' => now()->addDays(7),
            'is_active' => true,
        ]);

        $this->assertFalse($section->isVisible());
        $this->assertTrue($section->status->isScheduled());
    }

    #[Test]
    public function only_published_sections_are_visible_frontend(): void
    {
        // Create sections with different statuses
        Section::create([
            'type' => 'hero', 'location' => 'homepage', 'title' => ['en' => 'Draft'],
            'content' => ['en' => 'Draft'], 'status' => SectionStatus::Draft, 'is_active' => true,
        ]);

        Section::create([
            'type' => 'banner', 'location' => 'homepage', 'title' => ['en' => 'Published'],
            'content' => ['en' => 'Published'], 'status' => SectionStatus::Published, 'is_active' => true,
        ]);

        Section::create([
            'type' => 'cta', 'location' => 'homepage', 'title' => ['en' => 'Archived'],
            'content' => ['en' => 'Archived'], 'status' => SectionStatus::Archived, 'is_active' => true,
        ]);

        // Only published should be visible
        $visible = Section::published()->get();
        $this->assertEquals(1, $visible->count());
        $this->assertEquals('Published', $visible->first()->title['en']);
    }

    #[Test]
    public function section_status_enum_has_correct_labels(): void
    {
        $this->assertEquals('Draft', SectionStatus::Draft->label());
        $this->assertEquals('Published', SectionStatus::Published->label());
        $this->assertEquals('Scheduled', SectionStatus::Scheduled->label());
        $this->assertEquals('Archived', SectionStatus::Archived->label());
    }

    #[Test]
    public function published_status_is_active(): void
    {
        $this->assertTrue(SectionStatus::Published->isActive());
        $this->assertFalse(SectionStatus::Draft->isActive());
        $this->assertFalse(SectionStatus::Scheduled->isActive());
        $this->assertFalse(SectionStatus::Archived->isActive());
    }

    #[Test]
    public function section_can_be_published_programmatically(): void
    {
        $section = Section::create([
            'type' => 'hero', 'location' => 'homepage', 'title' => ['en' => 'Hero'],
            'content' => ['en' => 'Content'], 'status' => SectionStatus::Draft, 'is_active' => true,
        ]);

        $section->publish(1); // Admin ID 1

        $this->assertEquals(SectionStatus::Published, $section->fresh()->status);
        $this->assertEquals(1, $section->fresh()->published_by);
    }

    #[Test]
    public function section_can_be_archived(): void
    {
        $section = Section::create([
            'type' => 'hero', 'location' => 'homepage', 'title' => ['en' => 'Hero'],
            'content' => ['en' => 'Content'], 'status' => SectionStatus::Published, 'is_active' => true,
        ]);

        $section->archive();

        $this->assertEquals(SectionStatus::Archived, $section->fresh()->status);
    }

    #[Test]
    public function scheduled_sections_become_visible_when_publish_time_passes(): void
    {
        $section = Section::create([
            'type' => 'banner', 'location' => 'homepage', 'title' => ['en' => 'Scheduled'],
            'content' => ['en' => 'Content'], 'status' => SectionStatus::Published,
            'publish_at' => now()->subMinute(), // Already passed - should be visible
            'is_active' => true,
        ]);

        $this->assertTrue($section->isVisible());
    }
}
