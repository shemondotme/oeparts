<?php

namespace App\Filament\Pages\Settings;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;

class PdpSettings extends SettingsPage
{
    protected static ?string $title = 'Product Detail Page Settings';

    protected static string $settingsGroup = 'pdp';

    protected static ?int $navigationSort = 37;

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-document-text';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Product Page Sections')
                    ->description('Toggle individual content blocks on the storefront product detail page. Each toggle takes effect immediately (subject to the same 5-minute settings cache as every other settings group).')
                    ->schema([
                        Forms\Components\Toggle::make('show_specifications')
                            ->label('Specifications Table')
                            ->helperText('Key/value spec table, shown only when a product actually has specifications entered')
                            ->default(true),

                        Forms\Components\Toggle::make('show_warranty')
                            ->label('Warranty Block')
                            ->helperText('Shown only when a product has a warranty period set')
                            ->default(true),

                        Forms\Components\Toggle::make('show_video')
                            ->label('Product Video')
                            ->helperText('Shown only when a product has a video URL set')
                            ->default(true),

                        Forms\Components\Toggle::make('show_reviews')
                            ->label('Customer Reviews')
                            ->helperText('Public submission form plus approved-review display; new reviews still require admin approval regardless of this toggle')
                            ->default(true),

                        Forms\Components\Toggle::make('show_related_products')
                            ->label('Related Products')
                            ->helperText('Automatic — same manufacturer or shared vehicle fitment')
                            ->default(true),

                        Forms\Components\Toggle::make('sticky_add_to_cart')
                            ->label('Sticky Mobile Add-to-Cart Bar')
                            ->helperText('Keeps quantity + Add to Cart (and Buy Now, if enabled) visible while scrolling on mobile')
                            ->default(true),

                        Forms\Components\Toggle::make('buy_now_enabled')
                            ->label('"Buy Now" One-Click Checkout Button')
                            ->helperText('Skips the cart page — takes the customer straight to checkout with just this item')
                            ->default(false),

                        Forms\Components\TextInput::make('review_rate_limit_per_hour')
                            ->label('Review Submission Rate Limit (per Hour, per IP)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(100)
                            ->required()
                            ->helperText('Guards the public review form against spam submissions')
                            ->default(5),
                    ])->columns(2),
            ]);
    }
}
