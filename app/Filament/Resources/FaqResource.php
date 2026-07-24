<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FaqResource\Pages;
use App\Filament\Support\AdminUi;
use App\Models\Faq;
use Filament\Forms;
use Filament\Actions;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Notifications\NotificationAction;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Filament\Support\Enums\FontWeight;

class FaqResource extends Resource
{
    protected static ?string $model = Faq::class;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-question-mark-circle';
    }

    protected static ?string $cluster = \App\Filament\Clusters\Content::class;

    public static function getNavigationGroup(): ?string
    {
        return null;
    }

    public static function getNavigationSort(): ?int
    {
        return 40;
    }

    public static function getRecordTitleAttribute(): ?string
    {
        return null;
    }

    public static function getRecordTitle(?Model $record): string|null
    {
        return $record ? AdminUi::localizedName($record->question, 'FAQ') : null;
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
                                Section::make('FAQ Content')
                                    ->icon('heroicon-o-chat-bubble-left-right')
                                    ->description('Categorize this FAQ entry for organized display on the help center.')
                                    ->schema([
                                        Forms\Components\Select::make('category')
                                            ->label(__('admin.faq_category'))
                                            ->options([
                                                'shipping'  => 'Shipping',
                                                'ordering'  => 'Ordering',
                                                'returns'   => 'Returns',
                                                'payment'   => 'Payment',
                                                'products'  => 'Products',
                                                'general'   => 'General',
                                            ])
                                            ->native(false)
                                            ->nullable()
                                            ->helperText('Group related FAQs together for better organization.'),
                                    ]),

                                Section::make('Multilingual Question & Answer')
                                    ->icon('heroicon-o-language')
                                    ->description('Translate the FAQ question and answer in supported languages.')
                                    ->schema([
                                        AdminUi::translatableTabs('Locales', [
                                            'question' => [
                                                'label' => 'Question',
                                                'required' => true,
                                            ],
                                            'answer' => [
                                                'label' => 'Answer',
                                                'type' => 'richeditor',
                                            ],
                                        ]),
                                    ]),
                            ]),

                        // ─── Sidebar column ───────────────────────────────
                        Group::make()
                            ->columnSpan(['default' => 1, 'xl' => 1])
                            ->schema([
                                Section::make('Settings')
                                    ->icon('heroicon-o-adjustments-horizontal')
                                    ->description('FAQ visibility and display ordering.')
                                    ->schema([
                                        Forms\Components\Toggle::make('is_active')
                                            ->label(__('admin.faq_active'))
                                            ->helperText('Inactive FAQs are hidden from the storefront help center.')
                                            ->default(true),
                                        Forms\Components\TextInput::make('sort_order')
                                            ->label(__('admin.display_order'))
                                            ->numeric()
                                            ->default(0)
                                            ->minValue(0)
                                            ->helperText('Lower numbers appear first in the FAQ list.'),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return AdminUi::configureTable($table)
            ->columns([
            Tables\Columns\TextColumn::make('question')
                ->label(__('admin.question'))
                ->getStateUsing(fn (Faq $record): string => AdminUi::localizedName($record->question))
                ->searchable(query: function (Builder $query, string $search): Builder {
                    return $query->where(function ($q) use ($search) {
                        foreach (array_keys(AdminUi::LOCALES) as $code) {
                            $q->orWhere("question->{$code}", 'like', "%{$search}%");
                        }
                    });
                })
                ->sortable()
                ->weight(FontWeight::Medium)
                ->limit(50),
                Tables\Columns\TextColumn::make('category')
                    ->label(__('admin.category'))
                    ->badge()
                    ->color('gray')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('admin.active'))
                    ->boolean()
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label(__('admin.sort'))
                    ->fontMono()
                    ->alignCenter()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->label(__('admin.faq_category'))
                    ->options([
                        'shipping' => 'Shipping',
                        'ordering' => 'Ordering',
                        'returns'  => 'Returns',
                        'payment'  => 'Payment',
                        'products' => 'Products',
                        'general'  => 'General',
                    ])
                    ->native(false)
                    ->helperText('Filter FAQs by topic category.'),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('admin.faq_status'))
                    ->placeholder('All')
                    ->trueLabel('Active Only')
                    ->falseLabel('Inactive Only'),
            ])
            ->actions(AdminUi::recordActions(after: [
                Actions\Action::make('toggleActive')
                    ->label(fn (Faq $record): string => $record->is_active ? 'Deactivate' : 'Activate')
                    ->icon(fn (Faq $record): string => $record->is_active ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
                    ->color(fn (Faq $record): string => $record->is_active ? 'warning' : 'success')
                    ->authorize('update')
                    ->action(function (Faq $record) {
                        $record->update(['is_active' => !$record->is_active]);

                        Notification::make()
                            ->title($record->is_active ? 'FAQ activated' : 'FAQ deactivated')
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
                            $allSame = $records->every(fn (Faq $record) => $record->is_active === $firstState);
                            $newState = $allSame ? !$firstState : true;

                            $records->each(function (Faq $record) use ($newState) {
                                $record->update(['is_active' => $newState]);
                            });

                            Notification::make()
                                ->title($records->count() . ' FAQs ' . ($newState ? 'activated' : 'deactivated'))
                                ->success()
                                ->send();
                        },
                    ),
                AdminUi::exportCsvBulkAction('Export FAQs', [
                    'question' => 'Question',
                    'answer' => 'Answer',
                    'category' => 'Category',
                    'is_active' => 'Active',
                    'sort_order' => 'Sort Order',
                ]),
                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order', 'asc')
            ->reorderable('sort_order')
            ->emptyStateIcon('heroicon-o-question-mark-circle')
            ->emptyStateHeading('No FAQ entries created yet')
            ->emptyStateDescription('Create FAQ entries to help customers find answers to common questions about shipping, orders, and products.')
            ->emptyStateActions([
                Tables\Actions\Action::make('create')
                    ->label(__('admin.create_faq'))
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
            'index'  => Pages\ListFaqs::route('/'),
            'create' => Pages\CreateFaq::route('/create'),
            'view'   => Pages\ViewFaq::route('/{record}'),
            'edit'   => Pages\EditFaq::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['question', 'answer'];
    }
}

