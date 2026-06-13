<?php

namespace App\Filament\Resources\NewsletterSubscriberResource\Pages;

use App\Filament\Resources\NewsletterSubscriberResource;
use App\Filament\Support\AdminUi;
use Filament\Actions;
use Filament\Infolists;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewNewsletterSubscriber extends ViewRecord
{
    protected static string $resource = NewsletterSubscriberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
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
                                Section::make('Subscriber details')
                                    ->icon('heroicon-o-envelope')
                                    ->schema([
                                        TextEntry::make('email')
                                            ->label('Email')
                                            ->copyable(),
                                        TextEntry::make('lang')
                                            ->label('Language')
                                            ->badge()
                                            ->formatStateUsing(fn (string $state): string => AdminUi::LOCALES[$state] ?? strtoupper($state))
                                            ->color('gray'),
                                    ])
                                    ->columns(2),

                                Section::make('Connection details')
                                    ->icon('heroicon-o-computer-desktop')
                                    ->schema([
                                        TextEntry::make('ip_address')
                                            ->label('IP Address')
                                            ->placeholder('—'),
                                    ]),
                            ]),

                        // ─── Sidebar column ───────────────────────────────
                        Group::make()
                            ->columnSpan(['default' => 1, 'xl' => 1])
                            ->schema([
                                Section::make('Status & timing')
                                    ->icon('heroicon-o-adjustments-horizontal')
                                    ->schema([
                                        TextEntry::make('is_active')
                                            ->label('Status')
                                            ->badge()
                                            ->color(fn (bool $state): string => $state ? 'success' : 'gray')
                                            ->icon(fn (bool $state): string => $state ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
                                            ->formatStateUsing(fn (bool $state): string => $state ? 'Active' : 'Inactive'),
                                        TextEntry::make('subscribed_at')
                                            ->label('Subscribed At')
                                            ->dateTime('M j, Y H:i'),
                                        TextEntry::make('unsubscribed_at')
                                            ->label('Unsubscribed At')
                                            ->dateTime('M j, Y H:i')
                                            ->placeholder('—'),
                                    ]),

                                Section::make('Record')
                                    ->icon('heroicon-o-clock')
                                    ->schema([
                                        TextEntry::make('created_at')
                                            ->label('Created')
                                            ->dateTime('M j, Y H:i'),
                                        TextEntry::make('updated_at')
                                            ->label('Last updated')
                                            ->since(),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}
