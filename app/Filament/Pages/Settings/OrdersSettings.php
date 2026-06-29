<?php

namespace App\Filament\Pages\Settings;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;

class OrdersSettings extends SettingsPage
{
    protected static ?string $title = 'Order Settings';

    protected static string $settingsGroup = 'orders';

    protected static ?int $navigationSort = 32;

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-shopping-bag';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Order Processing Windows & Policies')
                    ->description('Govern cancellation windows, refund defaults, bank transfer expiration, and checkout thresholds.')
                    ->schema([
                        Forms\Components\TextInput::make('bank_transfer_expiry_hours')
                            ->label('Bank Transfer Payment Limit (Hours)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(720)
                            ->required()
                            ->helperText('Hours pending bank orders stay active before automatic expiry cancellation')
                            ->default(48),

                        Forms\Components\TextInput::make('customer_cancel_window_hours')
                            ->label('Client Cancel Grace Period (Hours)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(168)
                            ->required()
                            ->helperText('Hours buyers can cancel their order directly from their account portal')
                            ->default(2),

                        Forms\Components\TextInput::make('refund_window_days')
                            ->label('Portal RMA Return Limit (Days)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(90)
                            ->required()
                            ->helperText('Days from order fulfillment that buyers can request returns/refunds')
                            ->default(14),

                        Forms\Components\TextInput::make('minimum_order_amount')
                            ->label('Minimum Store Order Value (€)')
                            ->numeric()
                            ->minValue(0)
                            ->prefix('€')
                            ->helperText('Set to 0 to disable minimum order requirement')
                            ->default(0),

                        Forms\Components\TextInput::make('auto_complete_days')
                            ->label('Auto-Complete Order Fulfillment (Days)')
                            ->helperText('Orders automatically marked as delivered after this many days from shipment')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(90)
                            ->default(14),

                        Forms\Components\Toggle::make('urgent_processing_enabled')
                            ->label('Enable Fast-Track Processing Option')
                            ->helperText('Gives customers the option to request prioritized warehouse packing for a fee')
                            ->default(false),

                        Forms\Components\TextInput::make('urgent_processing_fee')
                            ->label('Urgent Processing Surcharge (€)')
                            ->numeric()
                            ->minValue(0)
                            ->prefix('€')
                            ->default(5.00),

                        Forms\Components\TextInput::make('expected_delivery_days')
                            ->label('Expected Supplier Delivery (Days)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(90)
                            ->required()
                            ->helperText('Baseline days used to score supplier on-time delivery performance')
                            ->default(5),
                    ])->columns(2),

                Section::make('Documents & Numbering Prefixes')
                    ->description('Set numbering patterns, prefix strings, and sequence padding digits.')
                    ->schema([
                        Forms\Components\TextInput::make('order_number_prefix')
                            ->label('Order Number Prefix')
                            ->maxLength(10)
                            ->placeholder('ORD')
                            ->default('ORD'),

                        Forms\Components\TextInput::make('order_number_padding')
                            ->label('Number Sequence Zero Padding')
                            ->numeric()
                            ->minValue(3)
                            ->maxValue(10)
                            ->helperText('Pad sequence digits with leading zeros (e.g. 6 digits = ORD-000123)')
                            ->default(6),

                        Forms\Components\TextInput::make('invoice_number_prefix')
                            ->label('Invoice Number Prefix')
                            ->maxLength(10)
                            ->placeholder('INV')
                            ->default('INV'),

                        Forms\Components\TextInput::make('rma_number_prefix')
                            ->label('RMA Return Ticket Prefix')
                            ->maxLength(10)
                            ->placeholder('RMA')
                            ->default('RMA'),
                    ])->columns(2),
            ]);
    }
}
