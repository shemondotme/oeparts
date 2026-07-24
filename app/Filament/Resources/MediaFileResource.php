<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MediaFileResource\Pages;
use App\Filament\Support\AdminUi;
use App\Models\MediaFile;
use Filament\Forms;
use Filament\Actions;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Support\Enums\FontWeight;

class MediaFileResource extends Resource
{
    protected static ?string $model = MediaFile::class;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-photo';
    }

    protected static ?string $cluster = \App\Filament\Clusters\Content::class;

    public static function getNavigationGroup(): ?string
    {
        return null;
    }

    public static function getNavigationSort(): ?int
    {
        return 60;
    }

    public static function getRecordTitleAttribute(): ?string
    {
        return 'file_name';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(['default' => 1, 'xl' => 3])
                    ->columnSpanFull()
                    ->schema([
                        // ─── Main column ──────────────────────────────────
                        Group::make()
                            ->columnSpan(['default' => 1, 'xl' => 2])
                            ->schema([
                                Section::make('File Info')
                                    ->icon('heroicon-o-photo')
                                    ->description('Metadata for this media file used across the platform.')
                                    ->schema([
                                        Forms\Components\TextInput::make('file_name')
                                            ->label(__('admin.file_name'))
                                            ->placeholder('e.g. brake-pad-diagram.jpg')
                                            ->required()
                                            ->maxLength(255)
                                            ->helperText('Descriptive name for this file. Used for identification.'),
                                        Forms\Components\TextInput::make('alt_text')
                                            ->label(__('admin.alt_text'))
                                            ->placeholder('e.g. Diagram showing brake pad thickness measurement')
                                            ->maxLength(255)
                                            ->nullable()
                                            ->helperText('Describe the image for screen readers and search engines. Important for accessibility and SEO.'),
                                        Forms\Components\TextInput::make('caption')
                                            ->label(__('admin.caption'))
                                            ->placeholder('e.g. Brake pad thickness measurement guide')
                                            ->maxLength(255)
                                            ->nullable()
                                            ->helperText('Optional caption text shown beneath the media file on the storefront.'),
                                    ]),
                            ]),

                        // ─── Sidebar column ───────────────────────────────
                        Group::make()
                            ->columnSpan(['default' => 1, 'xl' => 1])
                            ->schema([
                                Section::make('File Details')
                                    ->icon('heroicon-o-document-text')
                                    ->description('Technical information about this uploaded file.')
                                    ->schema([
                                        Forms\Components\TextInput::make('file_url')
                                            ->label(__('admin.file_url'))
                                            ->disabled()
                                            ->dehydrated(false)
                                            // copyMessage() doesn't exist on form inputs — it 500'd
                                            // this whole edit page (copyable() alone is fine).
                                            ->copyable()
                                            ->helperText('Full URL to access this file.'),
                                        Forms\Components\TextInput::make('mime_type')
                                            ->label(__('admin.mime_type'))
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->helperText('The file format type (e.g. image/jpeg, application/pdf).'),
                                        Forms\Components\TextInput::make('size')
                                            ->label(__('admin.file_size'))
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->formatStateUsing(fn (?int $state): string => $state ? number_format($state / 1024, 1) . ' KB' : '—')
                                            ->helperText('File size in kilobytes.'),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return AdminUi::configureTable($table)
            ->modifyQueryUsing(fn ($query) => $query->with('uploader'))
            ->columns([
                Tables\Columns\ImageColumn::make('file_url')
                    ->label(__('admin.preview'))
                    ->height(50)
                    ->width(50)
                    ->square(),
                Tables\Columns\TextColumn::make('file_name')
                    ->label(__('admin.file_name'))
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Medium)
                    ->limit(40),
                Tables\Columns\TextColumn::make('mime_type')
                    ->label(__('admin.type'))
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('size')
                    ->label(__('admin.size'))
                    ->formatStateUsing(fn (?int $state): string => $state ? number_format($state / 1024, 1) . ' KB' : '—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('alt_text')
                    ->label(__('admin.alt_text'))
                    ->placeholder('—')
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('uploader.name')
                    ->label(__('admin.uploaded_by'))
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('admin.uploaded'))
                    ->dateTime('M j, Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('mime_type')
                    ->label(__('admin.file_type'))
                    ->options([
                        'image'  => 'Images',
                        'application' => 'Documents',
                        'video'  => 'Video',
                    ])
                    ->query(fn ($query, $data): mixed => ($data['value'] ?? null) ? $query->where('mime_type', 'like', "{$data['value']}/%") : $query)
                    ->helperText('Filter by image, document, or video files.'),
            ])
            ->headerActions([
                static::makeUploadAction(),
            ])
            ->actions(AdminUi::recordActionsWithoutView())
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    AdminUi::exportCsvBulkAction('Export Media Files', [
                        'file_name' => 'File Name',
                        'mime_type' => 'Type',
                        'size' => 'Size',
                        'alt_text' => 'Alt Text',
                        'uploader.name' => 'Uploaded By',
                        'created_at' => 'Uploaded',
                    ]),
                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateIcon('heroicon-o-photo')
            ->emptyStateHeading('No media files uploaded yet')
            ->emptyStateDescription('Upload files here, or attach them while editing products, blog posts, and pages.')
            ->emptyStateActions([
                static::makeUploadAction()
                    ->button(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListMediaFiles::route('/'),
            'edit'   => Pages\EditMediaFile::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        // No create PAGE — uploads happen via the modal Upload action below.
        return false;
    }

    /**
     * Modal upload into the media library. Mirrors MediaPickerController's
     * conventions (public disk, media/ directory, image types, 5MB cap).
     */
    public static function makeUploadAction(): Actions\Action
    {
        return Actions\Action::make('upload')
            ->label(__('admin.upload_file'))
            ->icon('heroicon-o-arrow-up-tray')
            ->authorize(fn (): bool => auth('admin')->user()?->can('create', MediaFile::class) ?? false)
            ->modalHeading('Upload Media File')
            ->modalDescription('Add an image to the media library. It becomes selectable everywhere media is used (logos, featured images, social share images).')
            ->schema([
                Forms\Components\FileUpload::make('file')
                    ->label(__('admin.image'))
                    ->disk('public')
                    ->directory(fn (): string => 'media/' . now()->format('Y/m'))
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/gif', 'image/webp'])
                    ->maxSize(5120)
                    ->required()
                    ->storeFileNamesIn('original_name')
                    ->helperText('JPEG, PNG, GIF, or WebP. Max 5 MB.')
                    ->rules([
                        function () {
                            return function (string $attribute, $value, \Closure $fail) {
                                try {
                                    app(\App\Services\UploadedImageSanitizer::class)->assertSafe($value);
                                } catch (\InvalidArgumentException $e) {
                                    $fail($e->getMessage());
                                }
                            };
                        },
                    ])
                    // Same content-safety check + GD re-encode as every other
                    // upload endpoint (see UploadedImageSanitizer) — run after
                    // Filament's own default save so its directory/naming/
                    // move-vs-copy logic is untouched.
                    ->saveUploadedFileUsing(function (Forms\Components\FileUpload $component, $file) {
                        $path = $component->saveUploadedFile($file);

                        if ($path) {
                            app(\App\Services\UploadedImageSanitizer::class)
                                ->sanitize('public', $path, $component->getDisk()->mimeType($path) ?: null);
                        }

                        return $path;
                    }),
                Forms\Components\TextInput::make('alt_text')
                    ->label(__('admin.alt_text'))
                    ->maxLength(255)
                    ->nullable()
                    ->helperText('Describe the image for screen readers and SEO.'),
            ])
            ->action(function (array $data): void {
                $path = $data['file'];
                $disk = \Illuminate\Support\Facades\Storage::disk('public');

                MediaFile::create([
                    'uploaded_by' => auth('admin')->id(),
                    'file_name'   => $data['original_name'] ?? basename($path),
                    'file_path'   => $path,
                    // $disk (public), not the bare Storage::url() facade call —
                    // that resolves against config('filesystems.default'), which
                    // is 'local' here (a private disk with no url mapping), not
                    // the public disk this file actually lives on.
                    'file_url'    => $disk->url($path),
                    'mime_type'   => $disk->mimeType($path) ?: 'application/octet-stream',
                    'size'        => $disk->size($path) ?: null,
                    'alt_text'    => $data['alt_text'] ?? null,
                ]);

                \Filament\Notifications\Notification::make()
                    ->title('File uploaded')
                    ->body('The file is now available in the media library.')
                    ->success()
                    ->send();
            });
    }

    public static function getNavigationBadge(): ?string
    {
        return null;
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['file_name', 'alt_text'];
    }
}

