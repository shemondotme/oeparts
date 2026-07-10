<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\MediaFile;
use App\Models\Section;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ContentModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            \Database\Seeders\SettingsSeeder::class,
            \Database\Seeders\RolesSeeder::class,
            \Database\Seeders\AdminSeeder::class,
        ]);

        $this->actingAs(Admin::where('email', 'superadmin@oeparts.test')->firstOrFail(), 'admin');
    }

    private function makeMediaFile(): MediaFile
    {
        return MediaFile::create([
            'uploaded_by' => Admin::first()->id,
            'file_name'   => 'test.png',
            'file_path'   => 'media/test.png',
            'file_url'    => '/storage/media/test.png',
            'mime_type'   => 'image/png',
            'size'        => 2048,
        ]);
    }

    public function test_media_list_renders_with_records_present(): void
    {
        // Regression: recordUrl built a 'view' route MediaFileResource never
        // registers -> RouteNotFoundException during table render, but ONLY
        // once the table had rows. Always test lists WITH data.
        $this->makeMediaFile();

        Livewire::test(\App\Filament\Resources\MediaFileResource\Pages\ListMediaFiles::class)
            ->loadTable()
            ->assertOk()
            ->assertSee('test.png');
    }

    public function test_media_edit_page_renders(): void
    {
        // Regression: TextInput::copyMessage() doesn't exist -> 500.
        $file = $this->makeMediaFile();

        Livewire::test(\App\Filament\Resources\MediaFileResource\Pages\EditMediaFile::class, ['record' => $file->id])
            ->assertOk();
    }

    public function test_upload_action_creates_a_media_record(): void
    {
        Storage::fake('public');

        Livewire::test(\App\Filament\Resources\MediaFileResource\Pages\ListMediaFiles::class)
            ->loadTable()
            ->callTableAction('upload', data: [
                'file'     => UploadedFile::fake()->image('brake-diagram.png', 200, 200),
                'alt_text' => 'Brake diagram',
            ]);

        $record = MediaFile::where('alt_text', 'Brake diagram')->first();
        $this->assertNotNull($record);
        $this->assertSame('image/png', $record->mime_type);
        Storage::disk('public')->assertExists($record->file_path);
    }
}
