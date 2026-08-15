<?php

namespace App\Filament\Resources\TranslationResource\Pages;

use App\Filament\Resources\TranslationResource;
use App\Filament\Support\AdminUi;
use App\Models\LanguageString;
use Filament\Actions;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewTranslation extends ViewRecord
{
    protected static string $resource = TranslationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }

    /**
     * Custom infolist (previously auto-derived from the Resource's form())
     * so the shadow-status warning has somewhere to render — see
     * TranslationResource::isShadowed()'s docblock for what it means and
     * why it matters here specifically.
     */
    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Translation Details')
                    ->icon('heroicon-o-language')
                    ->schema([
                        TextEntry::make('lang_code')
                            ->label(__('admin.target_language'))
                            ->formatStateUsing(fn (string $state): string => AdminUi::LOCALES[$state] ?? strtoupper($state))
                            ->badge(),
                        TextEntry::make('group')
                            ->label(__('admin.translation_group'))
                            ->badge()
                            ->color('info'),
                        TextEntry::make('key')
                            ->label(__('admin.translation_key')),
                        TextEntry::make('value')
                            ->label(__('admin.translated_value'))
                            ->columnSpanFull(),
                    ])->columns(3),

                Section::make('Storefront Status')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->visible(fn (LanguageString $record): bool => TranslationResource::isShadowed($record))
                    ->schema([
                        TextEntry::make('shadow_status')
                            ->label('')
                            ->state('Shadowed by a Site Copy Library override')
                            ->badge()
                            ->color('warning')
                            ->icon('heroicon-o-exclamation-triangle')
                            ->helperText('A cart/search/navbar text override exists in the Site Copy Library for this exact key and locale, and takes precedence over this row — ui_copy() checks that override before ever falling back to this translation. Editing this value here will not change what customers see until the override is cleared or emptied.'),
                    ]),

                Section::make('Record')
                    ->icon('heroicon-o-clock')
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Created')
                            ->dateTime('M j, Y H:i'),
                        TextEntry::make('updated_at')
                            ->label('Updated')
                            ->since(),
                    ])->columns(2),
            ]);
    }
}
