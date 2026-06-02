<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MediaFileResource\Pages;
use App\Models\MediaFile;
use Filament\Forms;
use Filament\Actions;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class MediaFileResource extends Resource
{
    protected static ?string $model = MediaFile::class;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-photo';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Content';
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
                Section::make('File Details')
                    ->schema([
                        Forms\Components\TextInput::make('file_name')
                            ->label('File Name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('alt_text')
                            ->label('Alt Text')
                            ->maxLength(255)
                            ->nullable()
                            ->helperText('For accessibility and SEO'),
                        Forms\Components\TextInput::make('caption')
                            ->label('Caption')
                            ->maxLength(255)
                            ->nullable(),
                        Forms\Components\TextInput::make('file_url')
                            ->label('URL')
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('mime_type')
                            ->label('MIME Type')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('size')
                            ->label('Size')
                            ->disabled()
                            ->dehydrated(false)
                            ->formatStateUsing(fn (?int $state): string => $state ? number_format($state / 1024, 1) . ' KB' : '—'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('file_url')
                    ->label('Preview')
                    ->height(60)
                    ->width(60)
                    ->square(),
                Tables\Columns\TextColumn::make('file_name')
                    ->label('File')
                    ->searchable()
                    ->limit(30),
                Tables\Columns\TextColumn::make('mime_type')
                    ->label('Type')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('size')
                    ->label('Size')
                    ->formatStateUsing(fn (?int $state): string => $state ? number_format($state / 1024, 1) . ' KB' : '—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('alt_text')
                    ->label('Alt Text')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('uploader.name')
                    ->label('Uploaded By')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Uploaded')
                    ->dateTime()
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
                    ->query(fn ($query, $data): mixed => ($data['value'] ?? null) ? $query->where('mime_type', 'like', "{$data['value']}/%") : $query),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50, 100]);
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
}
