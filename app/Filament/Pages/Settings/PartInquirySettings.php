<?php

namespace App\Filament\Pages\Settings;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;

class PartInquirySettings extends SettingsPage
{
    protected static ?string $title = 'Part Inquiry Settings';

    protected static string $settingsGroup = 'part_inquiry';

    protected static ?int $navigationSort = 17;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Inquiry Configuration')
                    ->description('Configure how part inquiries are handled and response expectations.')
                    ->schema([
                        Forms\Components\TextInput::make('response_hours')
                            ->label('Expected Response Time (hours)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(168)
                            ->helperText('Displayed to customers as estimated response time')
                            ->default(24),

                        Forms\Components\Toggle::make('guest_inquiries_allowed')
                            ->label('Allow Guest Inquiries')
                            ->helperText('Allow non-registered users to submit part inquiries')
                            ->default(true),

                        Forms\Components\TextInput::make('rate_limit_per_hour')
                            ->label('Max Inquiries Per IP Per Hour')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(100)
                            ->default(10),
                    ])->columns(2),
            ]);
    }
}
