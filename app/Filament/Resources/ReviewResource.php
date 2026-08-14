<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReviewResource\Pages;
use App\Filament\Support\AdminUi;
use App\Models\Review;
use Filament\Forms;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;

class ReviewResource extends Resource
{
    protected static ?string $model = Review::class;

    protected static ?string $slug = 'content/reviews';

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-star';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Content';
    }

    public static function getNavigationSort(): ?int
    {
        return 36;
    }

    public static function getRecordTitleAttribute(): ?string
    {
        return 'reviewer_name';
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['reviewer_name', 'title'];
    }

    public static function getNavigationBadge(): ?string
    {
        return \App\Support\NavBadge::count('reviews_pending', fn () => static::getModel()::where('status', 'pending')->count());
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Reviews awaiting moderation';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Review')
                    ->description('Public submissions land here as pending — only Approved reviews are shown on the storefront.')
                    ->schema([
                        Forms\Components\Select::make('product_id')
                            ->label('Product')
                            ->relationship('product', 'oem_number')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'pending' => 'Pending',
                                'approved' => 'Approved',
                                'rejected' => 'Rejected',
                            ])
                            ->native(false)
                            ->required()
                            ->default('pending'),
                        Forms\Components\TextInput::make('reviewer_name')
                            ->label('Reviewer Name')
                            ->required()
                            ->maxLength(100),
                        Forms\Components\Select::make('rating')
                            ->label('Rating')
                            ->options([
                                5 => '5 Stars ★★★★★',
                                4 => '4 Stars ★★★★☆',
                                3 => '3 Stars ★★★☆☆',
                                2 => '2 Stars ★★☆☆☆',
                                1 => '1 Star ★☆☆☆☆',
                            ])
                            ->native(false)
                            ->required(),
                        Forms\Components\TextInput::make('title')
                            ->label('Title')
                            ->maxLength(150)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('comment')
                            ->label('Comment')
                            ->required()
                            ->maxLength(2000)
                            ->rows(4)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('ip_address')
                            ->label('Submitted From IP')
                            ->disabled()
                            ->dehydrated(false),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return AdminUi::configureTable($table)
            ->modifyQueryUsing(fn ($query) => $query->with('product'))
            ->columns([
                Tables\Columns\TextColumn::make('product.oem_number')
                    ->label('Product')
                    ->placeholder('—')
                    ->searchable()
                    ->weight(FontWeight::Medium),
                Tables\Columns\TextColumn::make('reviewer_name')
                    ->label('Reviewer')
                    ->searchable(),
                Tables\Columns\TextColumn::make('rating')
                    ->label('Rating')
                    ->formatStateUsing(fn (int $state): string => str_repeat('★', $state) . str_repeat('☆', 5 - $state))
                    ->color('warning')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('comment')
                    ->label('Comment')
                    ->limit(60),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'warning',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Submitted')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ])
                    ->default('pending')
                    ->native(false),
            ])
            ->actions(AdminUi::recordActions(after: [
                Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Review $record): bool => $record->status !== 'approved')
                    ->authorize('update')
                    ->action(function (Review $record) {
                        $record->update(['status' => 'approved']);

                        Notification::make()
                            ->title('Review approved')
                            ->success()
                            ->send();
                    }),
                Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Review $record): bool => $record->status !== 'rejected')
                    ->authorize('update')
                    ->action(function (Review $record) {
                        $record->update(['status' => 'rejected']);

                        Notification::make()
                            ->title('Review rejected')
                            ->success()
                            ->send();
                    }),
            ]))
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    AdminUi::impactBulkAction(
                        name: 'bulkApprove',
                        label: 'Approve Selected',
                        color: 'success',
                        icon: 'heroicon-o-check-circle',
                        summary: fn (Review $record): ?array => $record->status === 'approved'
                            ? null
                            : ['key' => $record->reviewer_name, 'old' => ucfirst($record->status), 'new' => 'Approved'],
                        action: function ($records): void {
                            $count = 0;
                            foreach ($records as $record) {
                                if ($record->status !== 'approved') {
                                    $record->update(['status' => 'approved']);
                                    $count++;
                                }
                            }

                            Notification::make()
                                ->title("{$count} reviews approved")
                                ->success()
                                ->send();
                        },
                    ),
                    AdminUi::impactBulkAction(
                        name: 'bulkReject',
                        label: 'Reject Selected',
                        color: 'danger',
                        icon: 'heroicon-o-x-circle',
                        summary: fn (Review $record): ?array => $record->status === 'rejected'
                            ? null
                            : ['key' => $record->reviewer_name, 'old' => ucfirst($record->status), 'new' => 'Rejected'],
                        action: function ($records): void {
                            $count = 0;
                            foreach ($records as $record) {
                                if ($record->status !== 'rejected') {
                                    $record->update(['status' => 'rejected']);
                                    $count++;
                                }
                            }

                            Notification::make()
                                ->title("{$count} reviews rejected")
                                ->success()
                                ->send();
                        },
                    ),
                    AdminUi::exportCsvBulkAction('Export Reviews', [
                        'product.oem_number' => 'Product',
                        'reviewer_name' => 'Reviewer',
                        'rating' => 'Rating',
                        'title' => 'Title',
                        'comment' => 'Comment',
                        'status' => 'Status',
                        'created_at' => 'Submitted',
                    ]),
                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateIcon('heroicon-o-star')
            ->emptyStateHeading('No reviews yet')
            ->emptyStateDescription('Customer reviews submitted from product pages will appear here for moderation.');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReviews::route('/'),
            'view' => Pages\ViewReview::route('/{record}'),
            'edit' => Pages\EditReview::route('/{record}/edit'),
        ];
    }
}
