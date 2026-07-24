<?php

namespace Tests\Feature;

use App\Enums\SectionStatus;
use App\Models\Admin;
use App\Models\Section;
use App\Models\SectionVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CmsEditorFeaturesTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = Admin::factory()->create();
        \Spatie\Permission\Models\Permission::findOrCreate('edit sections', 'admin');
        \Spatie\Permission\Models\Permission::findOrCreate('delete media files', 'admin');
        $this->admin->givePermissionTo(['edit sections', 'delete media files']);
        $this->actingAs($this->admin, 'admin');
    }

    // ============= FEATURE 2: WYSIWYG RICH TEXT EDITOR =============

    #[Test]
    public function feature_2_rich_editor_uploads_images()
    {
        $this->post(route('admin.editor.upload-image'), [
            'file' => $this->fakeImageFile('test.jpg'),
        ])->assertJsonPath('success', true)
         ->assertJsonStructure(['success', 'location']);
    }

    #[Test]
    public function feature_2_rich_editor_validates_image_type()
    {
        $this->post(route('admin.editor.upload-image'), [
            'file' => $this->fakeFile('test.txt'),
        ])->assertStatus(422);
    }

    #[Test]
    public function feature_2_rich_editor_enforces_file_size_limit()
    {
        $this->post(route('admin.editor.upload-image'), [
            'file' => $this->fakeImageFile('huge.jpg', 10000), // 10MB
        ])->assertStatus(422);
    }

    #[Test]
    public function feature_2_rich_editor_generates_html_preview()
    {
        $html = '<h1>Test</h1><p>Content</p>';
        
        $this->post(route('admin.editor.preview-html'), [
            'html' => $html,
        ])->assertJsonPath('success', true)
         ->assertJsonPath('preview', $html);
    }

    // ============= FEATURE 3: LIVE PREVIEW =============

    #[Test]
    public function feature_3_live_preview_returns_rendered_content()
    {
        $section = Section::factory()->create([
            'content' => [
                'en' => ['headline' => 'Test Headline', 'description' => 'Test Description'],
            ],
        ]);

        $this->post(route('admin.cms.sections.preview', $section), [
            'content' => $section->content,
            'lang'    => 'en',
        ])->assertJsonPath('success', true)
         ->assertJsonStructure(['html']);
    }

    #[Test]
    public function feature_3_live_preview_respects_language()
    {
        $section = Section::factory()->create([
            'content' => [
                'en' => ['headline' => 'English Title'],
                'de' => ['headline' => 'German Title'],
            ],
        ]);

        $response = $this->post(route('admin.cms.sections.preview', $section), [
            'content' => $section->content,
            'lang'    => 'de',
        ])->assertJsonPath('success', true);

        $this->assertStringContainsString('German Title', $response->json('html'));
    }

    // ============= FEATURE 4: AUDIT TRAIL & VERSION HISTORY =============

    #[Test]
    public function feature_4_creates_version_on_section_creation()
    {
        $section = Section::factory()->create([
            'status' => SectionStatus::Published,
        ]);

        $section->saveVersion('created', $this->admin->id, 'Initial creation');

        $this->assertDatabaseHas('section_versions', [
            'section_id' => $section->id,
            'action'     => 'created',
            'created_by' => $this->admin->id,
        ]);
    }

    #[Test]
    public function feature_4_creates_version_on_section_update()
    {
        $section = Section::factory()->create();
        $originalTitle = $section->title;

        $section->update(['title' => array_map(fn($v) => 'Updated', $section->title)]);
        $section->saveVersion('updated', $this->admin->id, 'Title updated');

        $this->assertEquals(1, $section->versions()->count());
        $this->assertEquals('updated', $section->versions()->first()->action);
    }

    #[Test]
    public function feature_4_restores_section_from_version()
    {
        $section = Section::factory()->create([
            'title' => ['en' => 'Original', 'de' => 'Orginal'],
        ]);
        $section->saveVersion('created', $this->admin->id);

        $section->update(['title' => ['en' => 'Modified', 'de' => 'Modifiziert']]);
        $section->saveVersion('updated', $this->admin->id);

        $version = $section->versions()->where('action', 'created')->first();
        $section->restoreFromVersion($version);

        $this->assertEquals('Original', $section->fresh()->title['en']);
    }

    #[Test]
    public function feature_4_version_history_shows_all_changes()
    {
        $section = Section::factory()->create();
        
        $section->saveVersion('created', $this->admin->id);
        $section->update(['is_active' => true]);
        $section->saveVersion('updated', $this->admin->id, 'Activated');
        
        $section->archive();
        $section->saveVersion('archived', $this->admin->id);

        $this->assertEquals(3, $section->versions()->count());
        $this->assertDatabaseHas('section_versions', ['action' => 'archived']);
    }

    #[Test]
    public function feature_4_version_stores_complete_snapshot()
    {
        $data = [
            'title'   => ['en' => 'Test Section'],
            'content' => ['en' => ['headline' => 'Headline']],
            'status'  => SectionStatus::Published,
        ];

        $section = Section::factory()->create($data);
        $section->saveVersion('created', $this->admin->id);

        $version = $section->versions()->first();
        $this->assertNotNull($version->snapshot);
        $this->assertEquals('Test Section', $version->snapshot['title']['en']);
    }

    #[Test]
    public function feature_4_restore_version_page_requires_auth()
    {
        auth('admin')->logout();
        $section = Section::factory()->create();
        $version = SectionVersion::factory()->create(['section_id' => $section->id]);

        $this->post(route('admin.cms.sections.restore-version', [$section, $version]))
            ->assertRedirect(route('filament.admin.auth.login'));
    }

    // ============= FEATURE 5: MEDIA INTEGRATION =============

    #[Test]
    public function feature_5_media_picker_lists_uploaded_files()
    {
        $this->post(route('admin.cms.media-picker.upload'), [
            'file'     => $this->fakeImageFile('test1.jpg'),
            'alt_text' => 'Test Image',
        ])->assertJsonPath('success', true);

        $response = $this->get(route('admin.cms.media-picker.index'));
        
        $this->assertTrue($response->json('success'));
        $this->assertGreaterThan(0, $response->json('total'));
    }

    #[Test]
    public function feature_5_media_upload_stores_file_metadata()
    {
        $response = $this->post(route('admin.cms.media-picker.upload'), [
            'file'     => $this->fakeImageFile('test.jpg'),
            'alt_text' => 'My Image',
        ])->assertJsonPath('success', true);

        $this->assertDatabaseHas('media_files', [
            'alt_text' => 'My Image',
            'uploaded_by' => $this->admin->id,
        ]);
    }

    #[Test]
    public function feature_5_media_picker_searches_files()
    {
        $this->post(route('admin.cms.media-picker.upload'), [
            'file'     => $this->fakeImageFile('sunset.jpg'),
            'alt_text' => 'Beautiful Sunset',
        ]);

        $response = $this->get(route('admin.cms.media-picker.index', ['search' => 'sunset']));
        
        $this->assertTrue($response->json('success'));
        $this->assertGreaterThan(0, $response->json('total'));
    }

    #[Test]
    public function feature_5_media_deletion_removes_file()
    {
        $response = $this->post(route('admin.cms.media-picker.upload'), [
            'file' => $this->fakeImageFile('temp.jpg'),
        ])->json('file');

        $mediaId = $response['id'];

        $this->delete(route('admin.cms.media-picker.destroy', ['media' => $mediaId]))
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('media_files', ['id' => $mediaId]);
    }

    // ============= INTEGRATION TESTS =============

    #[Test]
    public function all_features_work_together_in_section_edit()
    {
        $section = Section::factory()->create([
            'status' => SectionStatus::Draft,
        ]);

        // 1. Verify version history exists
        $this->assertEquals(0, $section->versions()->count());

        // 2. Update section with new content
        $this->put(route('admin.cms.sections.update', $section), [
            'location'         => $section->location->value,
            'title'            => ['en' => 'New Title'],
            'content'          => ['en' => ['headline' => 'New Headline']],
            'status'           => SectionStatus::Published->value,
            'is_active'        => true,
            'sort_order'       => 1,
            'change_summary'   => 'Published with new content',
        ])->assertRedirect();

        // 3. Verify version was saved
        $this->assertEquals(1, $section->fresh()->versions()->count());

        // 4. Verify preview works
        $this->post(route('admin.cms.sections.preview', $section), [
            'content' => ['en' => ['headline' => 'Test']],
            'lang'    => 'en',
        ])->assertJsonPath('success', true);
    }

    // ============= HELPER METHODS =============

    private function fakeImageFile(string $name = 'image.jpg', int $kilobytes = 100)
    {
        return $this->fakeFileWithContent($name, 'image/jpeg', $kilobytes);
    }

    private function fakeFile(string $name = 'file.txt', int $kilobytes = 100)
    {
        return $this->fakeFileWithContent($name, 'text/plain', $kilobytes);
    }

    private function fakeFileWithContent(string $name, string $mimeType, int $kilobytes = 100)
    {
        return \Illuminate\Http\UploadedFile::fake()->create($name, $kilobytes, $mimeType);
    }
}
