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
                Section::make('Order Processing')
                    ->schema([
                        Forms\Components\TextInput::make('bank_transfer_expiry_hours')
                            ->label('Bank Transfer Expiry (hours)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(720)
                            ->required()
                            ->default(48),
                        Forms\Components\TextInput::make('customer_cancel_window_hours')
                            ->label('Customer Cancel Window (hours)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(168)
                            ->required()
                            ->default(2),
                        Forms\Components\TextInput::make('refund_window_days')
                            ->label('Refund Window (days)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(90)
                            ->required()
                            ->default(14),
                        Forms\Components\Toggle::make('urgent_processing_enabled')
                            ->label('Enable Urgent Processing')
                            ->default(false),
                        Forms\Components\TextInput::make('urgent_processing_fee')
                            ->label('Urgent Processing Fee (€)')
                            ->numeric()
                            ->minValue(0)
                            ->prefix('€')
                            ->default(5.00),
                        Forms\Components\TextInput::make('minimum_order_amount')
                            ->label('Minimum Order Amount (€)')
                            ->numeric()
                            ->minValue(0)
                            ->prefix('€')
                            ->default(0),
                        Forms\Components\TextInput::make('auto_complete_days')
                            ->label('Auto-Complete After (days)')
                            ->helperText('Orders automatically marked as delivered after this many days from shipment')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(90)
                            ->default(14),
                    ])->columns(2),

                Section::make('Numbering')
                    ->schema([
                        Forms\Components\TextInput::make('order_number_prefix')
                            ->label('Order Number Prefix')
                            ->maxLength(10)
                            ->default('ORD'),
                        Forms\Components\TextInput::make('order_number_padding')
                            ->label('Order Number Padding')
                            ->numeric()
                            ->minValue(3)
                            ->maxValue(10)
                            ->default(6),
                        Forms\Components\TextInput::make('invoice_number_prefix')
                            ->label('Invoice Number Prefix')
                            ->maxLength(10)
                            ->default('INV'),
                        Forms\Components\TextInput::make('rma_number_prefix')
                            ->label('RMA Number Prefix')
                            ->maxLength(10)
                            ->default('RMA'),
                    ])->columns(2),
            ]);
    }
}
