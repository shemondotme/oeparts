<?php

namespace App\Filament\Pages\Settings;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;

class SocialLinkSettings extends SettingsPage
{
    protected static ?string $title = 'Social Links';

    protected static string $settingsGroup = 'social_links';

    protected static ?int $navigationSort = 65;

    public static function getNavigationLabel(): string
    {
        return 'Social Links';
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-globe-alt';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Social Media Profiles')
                    ->description('Link your social media profiles. These appear in the storefront footer and social sharing metadata.')
                    ->schema([
                        Forms\Components\TextInput::make('facebook_url')
                            ->label('Facebook URL')
                            ->helperText('Canonical social link for footer display. Contact Settings has a quick-link shortcut to the same URL.')
                            ->url()
                            ->placeholder('https://facebook.com/yourpage')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('instagram_url')
                            ->label('Instagram URL')
                            ->url()
                            ->placeholder('https://instagram.com/yourpage')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('twitter_url')
                            ->label('X (Twitter) URL')
                            ->url()
                            ->placeholder('https://x.com/yourhandle')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('linkedin_url')
                            ->label('LinkedIn URL')
                            ->helperText('Canonical social link for footer display. Contact Settings has a quick-link shortcut to the same URL.')
                            ->url()
                            ->placeholder('https://linkedin.com/company/yourpage')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('youtube_url')
                            ->label('YouTube URL')
                            ->url()
                            ->placeholder('https://youtube.com/@yourchannel')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('tiktok_url')
                            ->label('TikTok URL')
                            ->url()
                            ->placeholder('https://tiktok.com/@yourhandle')
                            ->maxLength(255),
                    ])->columns(2),

                Section::make('Display Settings')
                    ->description('Control which social icons appear in the storefront footer.')
                    ->schema([
                        Forms\Components\Toggle::make('show_in_footer')
                            ->label('Show Social Icons in Footer')
                            ->default(true),
                        Forms\Components\Select::make('footer_icon_style')
                            ->label('Icon Style')
                            ->options([
                                'filled' => 'Filled',
                                'outlined' => 'Outlined',
                            ])
                            ->default('filled'),
                    ])->columns(2),
            ]);
    }
}
