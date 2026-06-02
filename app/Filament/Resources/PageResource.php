<?php

namespace App\Filament\Resources;

use App\Enums\ContentStatus;
use App\Filament\Resources\PageResource\Pages;
use App\Models\Admin;
use App\Models\Page;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Actions;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class PageResource extends Resource
{
    protected static ?string $model = Page::class;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-document-text';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Content';
    }

    public static function getNavigationSort(): ?int
    {
        return 30;
    }

    public static function getRecordTitleAttribute(): ?string
    {
        return 'title';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Page Content')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label('Title (JSON)')
                            ->helperText('e.g. {"en": "About Us", "de": "Über uns"}')
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, callable $set, ?string $operation) {
                                if ($operation === 'create' && is_string($state) && filled($state)) {
                                    $set('slug', Str::slug($state));
                                }
                            }),
                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->maxLength(200)
                            ->unique(ignoreRecord: true)
                            ->helperText('URL path — auto-generated from title'),
                        Forms\Components\Textarea::make('content')
                            ->label('Content (JSON)')
                            ->helperText('HTML content per language: {"en": "<p>...</p>", "de": "<p>...</p>"}')
                            ->rows(10)
                            ->nullable(),
                    ]),
                Section::make('SEO & Meta')
                    ->schema([
                        Forms\Components\TextInput::make('meta_title')
                            ->label('Meta Title (JSON)')
                            ->nullable(),
                        Forms\Components\Textarea::make('meta_description')
                            ->label('Meta Description (JSON)')
                            ->rows(3)
                            ->nullable(),
                    ])->columns(2),
                Section::make('Settings')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->options(ContentStatus::class)
                            ->required()
                            ->default('draft'),
                        Forms\Components\DateTimePicker::make('published_at')
                            ->label('Published At')
                            ->nullable(),
                        Forms\Components\Toggle::make('is_homepage')
                            ->label('Homepage')
                            ->default(false)
                            ->helperText('Only one page can be the homepage'),
                        Forms\Components\Toggle::make('is_header')
                            ->label('Show in Header')
                            ->default(false),
                        Forms\Components\Toggle::make('is_footer')
                            ->label('Show in Footer')
                            ->default(false),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Title')
                    ->getStateUsing(fn (Page $record): string => is_array($record->title) ? ($record->title['en'] ?? $record->title[array_key_first($record->title)] ?? '—') : ($record->title ?? '—'))
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function ($q) use ($search) {
                            $q->where('title->en', 'like', "%{$search}%")
                                ->orWhere('title->de', 'like', "%{$search}%");
                        });
                    })
                    ->limit(30),
                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug')
                    ->badge()
                    ->color('gray')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (ContentStatus $state): string => match ($state) {
                        ContentStatus::Published => 'success',
                        ContentStatus::Draft => 'warning',
                        ContentStatus::Archived => 'danger',
                    }),
                Tables\Columns\IconColumn::make('is_homepage')
                    ->label('Home')
                    ->boolean()
                    ->alignCenter(),
                Tables\Columns\IconColumn::make('is_header')
                    ->label('Header')
                    ->boolean()
                    ->alignCenter(),
                Tables\Columns\IconColumn::make('is_footer')
                    ->label('Footer')
                    ->boolean()
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('published_at')
                    ->label('Published')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(ContentStatus::class),
                Tables\Filters\TernaryFilter::make('is_homepage')
                    ->label('Homepage'),
                Tables\Filters\TernaryFilter::make('is_header')
                    ->label('Header'),
                Tables\Filters\TernaryFilter::make('is_footer')
                    ->label('Footer'),
            ])
            ->actions([
                Actions\ViewAction::make(),
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
            'index'  => Pages\ListPages::route('/'),
            'create' => Pages\CreatePage::route('/create'),
            'view'   => Pages\ViewPage::route('/{record}'),
            'edit'   => Pages\EditPage::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::where('status', ContentStatus::Published)->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }
}
