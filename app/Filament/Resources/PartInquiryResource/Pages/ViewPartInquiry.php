<?php

namespace App\Filament\Resources\PartInquiryResource\Pages;

use App\Enums\PartInquiryStatus;
use App\Filament\Resources\PartInquiryResource;
use Filament\Actions;
use Filament\Infolists;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewPartInquiry extends ViewRecord
{
    protected static string $resource = PartInquiryResource::class;

    public function getHeading(): string
    {
        return "Inquiry — {$this->getRecord()->oem_number}";
    }

    protected function getHeaderActions(): array
    {
        return [
            PartInquiryResource::makeMarkSourcedAction()
                ->after(fn () => $this->refreshFormData(['status'])),
            PartInquiryResource::makeMarkUnavailableAction()
                ->after(fn () => $this->refreshFormData(['status'])),
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
                                Section::make('Inquiry details')
                                    ->icon('heroicon-o-chat-bubble-left-right')
                                    ->schema([
                                        TextEntry::make('oem_number')
                                            ->label('OEM Number')
                                            ->url(fn ($record): string => \App\Filament\Resources\ProductResource::getUrl('index', ['tableSearch' => $record->oem_number]))
                                            ->color('primary')
                                            ->tooltip('Search the catalog for this OEM number')
                                            ->extraAttributes(['class' => 'font-mono uppercase']),
                                        TextEntry::make('quantity')
                                            ->label('Requested Quantity'),
                                        TextEntry::make('urgency')
                                            ->label('Urgency')
                                            ->badge()
                                            ->color(fn (string $state): string => match ($state) {
                                                'urgent' => 'danger',
                                                'soon' => 'warning',
                                                'normal' => 'success',
                                                default => 'gray',
                                            })
                                            ->icon(fn (string $state): string => match ($state) {
                                                'urgent' => 'heroicon-o-exclamation-triangle',
                                                'soon' => 'heroicon-o-clock',
                                                'normal' => 'heroicon-o-check-circle',
                                                default => '',
                                            })
                                            ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                                    ])
                                    ->columns(3),

                                Section::make('Vehicle information')
                                    ->icon('heroicon-o-wrench-screwdriver')
                                    ->schema([
                                        TextEntry::make('manufacturer')
                                            ->label('Manufacturer')
                                            ->placeholder('—'),
                                        TextEntry::make('car_model')
                                            ->label('Car Model')
                                            ->placeholder('—'),
                                        TextEntry::make('year')
                                            ->label('Year')
                                            ->placeholder('—'),
                                        TextEntry::make('vin_number')
                                            ->label('VIN Number')
                                            ->placeholder('—')
                                            ->copyable()
                                            ->extraAttributes(['class' => 'font-mono uppercase']),
                                    ])
                                    ->columns(2),

                                Section::make('Customer notes')
                                    ->icon('heroicon-o-document-text')
                                    ->schema([
                                        TextEntry::make('notes')
                                            ->hiddenLabel()
                                            ->placeholder('No notes provided by the customer.')
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        // ─── Sidebar column ───────────────────────────────
                        Group::make()
                            ->columnSpan(['default' => 1, 'xl' => 1])
                            ->schema([
                                Section::make('Status & processing')
                                    ->icon('heroicon-o-adjustments-horizontal')
                                    ->schema([
                                        TextEntry::make('status')
                                            ->label('Status')
                                            ->badge()
                                            ->color(fn (PartInquiryStatus $state): string => match ($state) {
                                                PartInquiryStatus::New        => 'gray',
                                                PartInquiryStatus::Reviewing  => 'warning',
                                                PartInquiryStatus::Sourced    => 'success',
                                                PartInquiryStatus::Unavailable => 'danger',
                                            })
                                            ->icon(fn (PartInquiryStatus $state): string => match ($state) {
                                                PartInquiryStatus::New        => 'heroicon-o-clock',
                                                PartInquiryStatus::Reviewing  => 'heroicon-o-eye',
                                                PartInquiryStatus::Sourced    => 'heroicon-o-check-circle',
                                                PartInquiryStatus::Unavailable => 'heroicon-o-x-circle',
                                            }),
                                        TextEntry::make('admin_note')
                                            ->label('Internal Admin Note')
                                            ->placeholder('—'),
                                    ]),

                                Section::make('Customer contact')
                                    ->icon('heroicon-o-user')
                                    ->schema([
                                        TextEntry::make('email')
                                            ->label('Email')
                                            ->copyable(),
                                        TextEntry::make('phone')
                                            ->label('Phone')
                                            ->placeholder('—')
                                            ->copyable(),
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
