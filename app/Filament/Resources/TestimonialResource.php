<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TestimonialResource\Pages;
use App\Filament\Support\AdminUi;
use App\Models\Testimonial;
use Filament\Forms;
use Filament\Actions;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TestimonialResource extends Resource
{
    protected static ?string $model = Testimonial::class;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-chat-bubble-left-right';
    }

    protected static ?string $cluster = \App\Filament\Clusters\Content::class;

    public static function getNavigationGroup(): ?string
    {
        return null;
    }

    public static function getNavigationSort(): ?int
    {
        return 35;
    }

    public static function getRecordTitleAttribute(): ?string
    {
        return 'name';
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'company'];
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
                                Section::make('Client Details')
                                    ->icon('heroicon-o-user')
                                    ->description('Information about the client giving the feedback.')
                                    ->schema([
                                        Forms\Components\TextInput::make('name')
                                            ->label(__('admin.customer_name'))
                                            ->placeholder('e.g. Jan de Vries')
                                            ->required()
                                            ->maxLength(100),
                                        Forms\Components\TextInput::make('company')
                                            ->label(__('admin.company_name'))
                                            ->placeholder('e.g. AutoParts B.V.')
                                            ->maxLength(100)
                                            ->nullable()
                                            ->helperText('Optional company or business name.'),
                                        Forms\Components\TextInput::make('location')
                                            ->label(__('admin.location'))
                                            ->placeholder('e.g. Amsterdam, Netherlands')
                                            ->maxLength(100)
                                            ->nullable()
                                            ->helperText('City and country of the client.'),
                                    ])
                                    ->columns(3),

                                Section::make('Client Testimonial')
                                    ->icon('heroicon-o-chat-bubble-bottom-center-text')
                                    ->description('The customer quote translated in each supported language.')
                                    ->schema([
                                        AdminUi::translatableTabs('Locales', [
                                            'quote' => [
                                                'label' => 'Testimonial Quote',
                                                'type' => 'textarea',
                                                'required' => true,
                                                'rows' => 4,
                                                'placeholder' => 'e.g. Excellent service and fast delivery. The parts fit perfectly!',
                                                'helperText' => 'English quote is required and used as the default fallback.',
                                            ],
                                        ]),
                                    ]),
                            ]),

                        // ─── Sidebar column ───────────────────────────────
                        Group::make()
                            ->columnSpan(['default' => 1, 'xl' => 1])
                            ->schema([
                                Section::make('Settings & Rating')
                                    ->icon('heroicon-o-adjustments-horizontal')
                                    ->description('Visibility, display order, and star rating for this testimonial.')
                                    ->schema([
                                        Forms\Components\Toggle::make('is_active')
                                            ->label(__('admin.testimonial_active'))
                                            ->helperText('Inactive testimonials are hidden from the storefront.')
                                            ->default(true),
                                        Forms\Components\TextInput::make('sort_order')
                                            ->label(__('admin.display_order'))
                                            ->numeric()
                                            ->default(0)
                                            ->minValue(0)
                                            ->helperText('Lower numbers appear first in testimonial listings.'),
                                        Forms\Components\Select::make('rating')
                                            ->label(__('admin.star_rating'))
                                            ->options([
                                                5 => '5 Stars ★★★★★',
                                                4 => '4 Stars ★★★★☆',
                                                3 => '3 Stars ★★★☆☆',
                                                2 => '2 Stars ★★☆☆☆',
                                                1 => '1 Star ★☆☆☆☆',
                                            ])
                                            ->native(false)
                                            ->required()
                                            ->default(5)
                                            ->helperText('Customer satisfaction rating shown with the testimonial.'),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return AdminUi::configureTable($table)
            ->columns([
            Tables\Columns\TextColumn::make('name')
                ->label(__('admin.client'))
                ->searchable()
                ->sortable()
                ->weight(FontWeight::Medium),
                Tables\Columns\TextColumn::make('company')
                    ->label(__('admin.company'))
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('location')
                    ->label(__('admin.location'))
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('quote')
                    ->label(__('admin.quote'))
                    ->getStateUsing(fn (Testimonial $record): string => AdminUi::localizedName($record->quote))
                    ->limit(60),
                Tables\Columns\TextColumn::make('rating')
                    ->label(__('admin.rating'))
                    ->formatStateUsing(fn (int $state): string => str_repeat('★', $state) . str_repeat('☆', 5 - $state))
                    ->color('warning')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('is_active')
                    ->label(__('admin.active'))
                    ->badge()
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray')
                    ->icon(fn (bool $state): string => $state ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Active' : 'Inactive')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label(__('admin.sort'))
                    ->fontMono()
                    ->alignCenter()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('admin.testimonial_status'))
                    ->placeholder('All')
                    ->trueLabel('Active Only')
                    ->falseLabel('Inactive Only')
                    ->native(false)
                    ->columnSpan(1),
                Tables\Filters\Filter::make('created_at')
                    ->label(__('admin.date_added'))
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label(__('admin.added_after'))
                            ->placeholder('Select start date'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label(__('admin.added_before'))
                            ->placeholder('Select end date'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['created_from'], fn ($q) => $q->whereDate('created_at', '>=', $data['created_from']))
                            ->when($data['created_until'], fn ($q) => $q->whereDate('created_at', '<=', $data['created_until']));
                    })
                    ->columnSpan(2),
            ])
            ->filtersFormColumns(2)
            ->actions(AdminUi::recordActions(after: [
                Actions\Action::make('toggleActive')
                    ->label(fn (Testimonial $record): string => $record->is_active ? 'Deactivate' : 'Activate')
                    ->icon(fn (Testimonial $record): string => $record->is_active ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
                    ->color(fn (Testimonial $record): string => $record->is_active ? 'warning' : 'success')
                    ->authorize('update')
                    ->action(function (Testimonial $record) {
                        $record->update(['is_active' => !$record->is_active]);

                        Notification::make()
                            ->title($record->is_active ? 'Testimonial activated' : 'Testimonial deactivated')
                            ->success()
                            ->send();
                    }),
            ]))
        ->bulkActions([
            Actions\BulkActionGroup::make([
                AdminUi::impactBulkAction(
                    name: 'bulkToggleActive',
                    label: 'Toggle Active',
                    color: 'warning',
                    icon: 'heroicon-o-arrow-path',
                    action: function ($records) {
                        $firstState = $records->first()->is_active;
                        $allSame = $records->every(fn (Testimonial $record) => $record->is_active === $firstState);
                        $newState = $allSame ? !$firstState : true;

                        $records->each(function (Testimonial $record) use ($newState) {
                            $record->update(['is_active' => $newState]);
                        });

                        Notification::make()
                            ->title($records->count() . ' testimonials ' . ($newState ? 'activated' : 'deactivated'))
                            ->success()
                            ->send();
                    },
                ),
                AdminUi::exportCsvBulkAction('Export Testimonials', [
                    'name' => 'Client',
                    'company' => 'Company',
                    'location' => 'Location',
                    'quote' => 'Quote',
                    'rating' => 'Rating',
                    'is_active' => 'Active',
                    'sort_order' => 'Sort Order',
                ]),
                Actions\DeleteBulkAction::make(),
            ]),
        ])
            ->defaultSort('sort_order', 'asc')
            ->reorderable('sort_order')
            ->emptyStateIcon('heroicon-o-chat-bubble-left-right')
            ->emptyStateHeading('No testimonials added yet')
            ->emptyStateDescription('Add customer testimonials and reviews to build trust and social proof on the storefront.')
            ->emptyStateActions([
                Tables\Actions\Action::make('create')
                    ->label(__('admin.add_testimonial'))
                    ->url(static::getUrl('create'))
                    ->icon('heroicon-o-plus')
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
            'index'  => Pages\ListTestimonials::route('/'),
            'create' => Pages\CreateTestimonial::route('/create'),
            'view'   => Pages\ViewTestimonial::route('/{record}'),
            'edit'   => Pages\EditTestimonial::route('/{record}/edit'),
        ];
    }
}
