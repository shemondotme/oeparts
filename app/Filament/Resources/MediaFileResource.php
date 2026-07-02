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
                                            ->label('File Name')
                                            ->placeholder('e.g. brake-pad-diagram.jpg')
                                            ->required()
                                            ->maxLength(255)
                                            ->helperText('Descriptive name for this file. Used for identification.'),
                                        Forms\Components\TextInput::make('alt_text')
                                            ->label('Alt Text')
                                            ->placeholder('e.g. Diagram showing brake pad thickness measurement')
                                            ->maxLength(255)
                                            ->nullable()
                                            ->helperText('Describe the image for screen readers and search engines. Important for accessibility and SEO.'),
                                        Forms\Components\TextInput::make('caption')
                                            ->label('Caption')
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
                                            ->label('File URL')
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->copyable()
                                            ->copyMessage('URL copied')
                                            ->helperText('Full URL to access this file. Click the copy icon to copy.'),
                                        Forms\Components\TextInput::make('mime_type')
                                            ->label('MIME Type')
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->helperText('The file format type (e.g. image/jpeg, application/pdf).'),
                                        Forms\Components\TextInput::make('size')
                                            ->label('File Size')
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
                    ->label('Preview')
                    ->height(50)
                    ->width(50)
                    ->square(),
                Tables\Columns\TextColumn::make('file_name')
                    ->label('File Name')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Medium)
                    ->limit(40),
                Tables\Columns\TextColumn::make('mime_type')
                    ->label('Type')
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('size')
                    ->label('Size')
                    ->formatStateUsing(fn (?int $state): string => $state ? number_format($state / 1024, 1) . ' KB' : '—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('alt_text')
                    ->label('Alt Text')
                    ->placeholder('—')
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('uploader.name')
                    ->label('Uploaded By')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Uploaded')
                    ->dateTime('M j, Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('mime_type')
                    ->label('File Type')
                    ->options([
                        'image'  => 'Images',
                        'application' => 'Documents',
                        'video'  => 'Video',
                    ])
                    ->query(fn ($query, $data): mixed => ($data['value'] ?? null) ? $query->where('mime_type', 'like', "{$data['value']}/%") : $query)
                    ->helperText('Filter by image, document, or video files.'),
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
            ->emptyStateDescription('Media files uploaded through products, categories, blog posts, and pages will appear here.');
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
        return false;
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

