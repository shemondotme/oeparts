<?php

namespace App\Filament\Resources;

use App\Enums\SectionLocation;
use App\Enums\SectionStatus;
use App\Filament\Resources\SectionResource\Pages;
use App\Models\Section;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Actions;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;
use Filament\Schemas\Components\Section as UiSection;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SectionResource extends Resource
{
    protected static ?string $model = Section::class;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-squares-2x2';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Content';
    }

    public static function getNavigationSort(): ?int
    {
        return 20;
    }

    public static function getRecordTitleAttribute(): ?string
    {
        return null;
    }

    public static function getRecordTitle(?Model $record): string|null
    {
        $title = $record->title;
        return is_array($title)
            ? ($title['en'] ?? $title[array_key_first($title)] ?? 'Section')
            : ($title ?? 'Section');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                UiSection::make('Section Details')
                    ->schema([
                        Forms\Components\Select::make('type')
                            ->label('Section Type')
                            ->options([
                                'hero_banner' => 'Hero Banner',
                                'trust_signals' => 'Trust Signals',
                                'how_it_works' => 'How It Works',
                                'stats_counter' => 'Stats Counter',
                                'promo_banner' => 'Promo Banner',
                                'newsletter' => 'Newsletter',
                                'manufacturer_carousel' => 'Manufacturer Carousel',
                                'popular_searches' => 'Popular Searches',
                                'blog_preview' => 'Blog Preview',
                                'faq_accordion' => 'FAQ Accordion',
                                'testimonials' => 'Testimonials',
                                'contact_strip' => 'Contact Strip',
                                'announcement_bar' => 'Announcement Bar',
                            ])
                            ->required()
                            ->live(),
                        Forms\Components\Select::make('location')
                            ->options(SectionLocation::class)
                            ->required(),
                        Forms\Components\TextInput::make('title')
                            ->label('Title (JSON)')
                            ->helperText('JSON format: {"en": "...", "de": "...", "lt": "...", "fr": "...", "es": "..."}')
                            ->nullable(),
                        Forms\Components\Textarea::make('content')
                            ->label('Content (JSON)')
                            ->helperText('JSON content structure varies by section type')
                            ->nullable()
                            ->rows(8)
                            ->columnSpanFull(),
                        Forms\Components\Select::make('status')
                            ->options(SectionStatus::class)
                            ->required()
                            ->default(SectionStatus::Draft),
                        Forms\Components\DateTimePicker::make('publish_at')
                            ->label('Publish At')
                            ->nullable(),
                        Forms\Components\TextInput::make('sort_order')
                            ->label('Sort Order')
                            ->numeric()
                            ->default(0),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color('gray')
                    ->getStateUsing(fn (Section $record): string => ucwords(str_replace('_', ' ', $record->type)))
                    ->searchable(),
                Tables\Columns\TextColumn::make('title')
                    ->label('Title')
                    ->getStateUsing(fn (Section $record): string => is_array($record->title) ? ($record->title['en'] ?? $record->title[array_key_first($record->title)] ?? '—') : ($record->title ?? '—'))
                    ->limit(30),
                Tables\Columns\TextColumn::make('location')
                    ->label('Location')
                    ->badge()
                    ->color(fn (SectionLocation $state): string => $state === SectionLocation::Homepage ? 'primary' : 'info')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (SectionStatus $state): string => match ($state) {
                        SectionStatus::Published => 'success',
                        SectionStatus::Draft     => 'gray',
                        SectionStatus::Scheduled => 'warning',
                        SectionStatus::Archived  => 'danger',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Sort')
                    ->alignCenter()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('publish_at')
                    ->label('Publish At')
                    ->dateTime('M j, Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'hero_banner' => 'Hero Banner',
                        'trust_signals' => 'Trust Signals',
                        'how_it_works' => 'How It Works',
                        'stats_counter' => 'Stats Counter',
                        'promo_banner' => 'Promo Banner',
                        'newsletter' => 'Newsletter',
                        'manufacturer_carousel' => 'Manufacturer Carousel',
                        'popular_searches' => 'Popular Searches',
                        'blog_preview' => 'Blog Preview',
                        'faq_accordion' => 'FAQ Accordion',
                        'testimonials' => 'Testimonials',
                        'contact_strip' => 'Contact Strip',
                        'announcement_bar' => 'Announcement Bar',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->options(SectionStatus::class),
                Tables\Filters\SelectFilter::make('location')
                    ->options(SectionLocation::class),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->reorderable('sort_order')
            ->actions([
                Actions\ViewAction::make(),
                Actions\EditAction::make(),
                Actions\Action::make('publish')
                    ->label('Publish')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Section $record): bool => $record->status !== SectionStatus::Published)
                    ->action(function (Section $record): void {
                        $record->publish();

                        Notification::make()
                            ->title('Section published')
                            ->success()
                            ->send();
                    }),
                Actions\Action::make('archive')
                    ->label('Archive')
                    ->icon('heroicon-o-archive-box')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->visible(fn (Section $record): bool => $record->status !== SectionStatus::Archived)
                    ->action(function (Section $record): void {
                        $record->archive();

                        Notification::make()
                            ->title('Section archived')
                            ->success()
                            ->send();
                    }),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order', 'asc')
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
            'index'  => Pages\ListSections::route('/'),
            'create' => Pages\CreateSection::route('/create'),
            'view'   => Pages\ViewSection::route('/{record}'),
            'edit'   => Pages\EditSection::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::where('status', \App\Enums\SectionStatus::Published)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }
}
