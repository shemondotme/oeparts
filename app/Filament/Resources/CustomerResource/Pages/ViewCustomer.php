<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use Filament\Actions;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewCustomer extends ViewRecord
{
    protected static string $resource = CustomerResource::class;

    /**
     * Order statuses that count as revenue — mirrors the list query.
     *
     * @return array<int, string>
     */
    private static function paidStatuses(): array
    {
        return [
            \App\Enums\OrderStatus::Paid->value,
            \App\Enums\OrderStatus::Processing->value,
            \App\Enums\OrderStatus::Shipped->value,
            \App\Enums\OrderStatus::Delivered->value,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\ActionGroup::make([
                Actions\DeleteAction::make(),
            ])
                ->icon('heroicon-o-ellipsis-vertical')
                ->color('gray'),
        ];
    }

    public function infolist(Schema $schema): Schema
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
                                Section::make('Contact Details')
                                    ->icon('heroicon-o-user')
                                    ->description('Primary contact information for this customer account.')
                                    ->schema([
                                        TextEntry::make('name')
                                            ->label('Full Name')
                                            ->weight('bold'),
                                        TextEntry::make('email')
                                            ->label('Email Address')
                                            ->copyable()
                                            ->color('primary'),
                                        TextEntry::make('phone')
                                            ->label('Phone Number')
                                            ->copyable()
                                            ->placeholder('—'),
                                        TextEntry::make('preferred_locale')
                                            ->label('Preferred Language')
                                            ->badge()
                                            ->placeholder('—'),
                                        TextEntry::make('timezone')
                                            ->label('Timezone')
                                            ->placeholder('—'),
                                    ])->columns(2),
                                Section::make('Order Summary')
                                    ->icon('heroicon-o-shopping-bag')
                                    ->description('Aggregated order statistics from the customer\'s purchase history. Spend figures count paid orders only.')
                                    ->schema([
                                        // The list query's withCount/withSum aliases are NOT
                                        // loaded on a ViewRecord — reading them here rendered
                                        // 0/€0.00 next to a visible order. Query directly.
                                        TextEntry::make('orders_count')
                                            ->label('Total Orders')
                                            ->state(fn ($record): string => (string) $record->orders()->count()),
                                        TextEntry::make('orders_sum_grand_total')
                                            ->label('Total Spent (paid)')
                                            ->state(fn ($record): string => format_money(
                                                (string) $record->orders()->whereIn('status', self::paidStatuses())->sum('grand_total')
                                            ))
                                            ->weight('bold')
                                            ->color('success'),
                                        TextEntry::make('orders_avg_grand_total')
                                            ->label('Avg Order (paid)')
                                            ->state(function ($record): string {
                                                $avg = $record->orders()->whereIn('status', self::paidStatuses())->avg('grand_total');

                                                return $avg ? format_money((string) $avg) : '—';
                                            }),
                                    ])->columns(3),
                            ]),

                        // ─── Sidebar column ───────────────────────────────
                        Group::make()
                            ->columnSpan(['default' => 1, 'xl' => 1])
                            ->schema([
                                Section::make('Account Status')
                                    ->icon('heroicon-o-shield-check')
                                    ->schema([
                                        TextEntry::make('is_active')
                                            ->label('Active')
                                            ->badge()
                                            ->getStateUsing(fn ($record): string => $record->is_active ? 'Active' : 'Inactive')
                                            ->color(fn (string $state): string => $state === 'Active' ? 'success' : 'gray')
                                            ->icon(fn (string $state): string => $state === 'Active' ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle'),
                                        TextEntry::make('last_login_at')
                                            ->label('Last Login')
                                            ->since()
                                            ->placeholder('Never logged in'),
                                        TextEntry::make('last_order_date')
                                            ->label('Last Order')
                                            ->getStateUsing(fn ($record): ?string => $record->orders->first()?->created_at?->diffForHumans())
                                            ->placeholder('No orders'),
                                    ]),
                                Section::make('Record')
                                    ->icon('heroicon-o-clock')
                                    ->schema([
                                        TextEntry::make('created_at')
                                            ->label('Registered')
                                            ->dateTime('M j, Y H:i')
                                            ->placeholder('—'),
                                        TextEntry::make('updated_at')
                                            ->label('Last Updated')
                                            ->since()
                                            ->placeholder('—'),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}
