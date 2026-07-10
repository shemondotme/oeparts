<?php

namespace App\Filament\Resources\ContactMessageResource\Pages;

use App\Enums\ContactStatus;
use App\Filament\Resources\ContactMessageResource;
use Filament\Actions;
use Filament\Infolists;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewContactMessage extends ViewRecord
{
    protected static string $resource = ContactMessageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('markResolved')
                ->label('Mark Resolved')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->authorize('update')
                ->requiresConfirmation()
                ->action(function () {
                    $this->record->update(['status' => 'resolved']);
                    $this->refreshFormData(['status']);

                    \Filament\Notifications\Notification::make()
                        ->title('Message marked as resolved')
                        ->success()
                        ->send();
                })
                ->visible(fn () => $this->record->status !== ContactStatus::Resolved),
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
                                Section::make('Message Content')
                                    ->icon('heroicon-o-envelope')
                                    ->schema([
                                        TextEntry::make('subject_type')
                                            ->label('Subject / Inquiry Type')
                                            ->columnSpanFull(),
                                        TextEntry::make('message')
                                            ->label('Message Body')
                                            ->placeholder('No message content.')
                                            ->columnSpanFull(),
                                    ]),

                                Section::make('Reply Sent')
                                    ->icon('heroicon-o-arrow-uturn-left')
                                    ->description('The reply emailed to the customer from this panel.')
                                    ->visible(fn ($record): bool => filled($record->reply_body))
                                    ->schema([
                                        TextEntry::make('reply_body')
                                            ->hiddenLabel()
                                            ->columnSpanFull(),
                                        TextEntry::make('repliedBy.name')
                                            ->label('Replied by')
                                            ->placeholder('—'),
                                        TextEntry::make('replied_at')
                                            ->label('Replied at')
                                            ->dateTime('M j, Y H:i'),
                                    ])
                                    ->columns(2),

                                Section::make('Part / Order Reference')
                                    ->icon('heroicon-o-magnifying-glass')
                                    ->description('Related part or order information, if provided by the customer.')
                                    ->collapsible()
                                    ->schema([
                                        TextEntry::make('order_number')
                                            ->label('Order Number')
                                            ->placeholder('—'),
                                        TextEntry::make('oem_number')
                                            ->label('OEM Number')
                                            ->extraAttributes(['class' => 'oem-number'])
                                            ->placeholder('—'),
                                        TextEntry::make('manufacturer')
                                            ->label('Manufacturer')
                                            ->placeholder('—'),
                                        TextEntry::make('car_model')
                                            ->label('Car Model')
                                            ->placeholder('—'),
                                    ])->columns(2),
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
                                            ->color(fn (ContactStatus $state): string => match ($state) {
                                                ContactStatus::Unread => 'danger',
                                                ContactStatus::Read => 'warning',
                                                ContactStatus::Resolved => 'success',
                                            })
                                            ->icon(fn (ContactStatus $state): string => match ($state) {
                                                ContactStatus::Unread => 'heroicon-o-envelope',
                                                ContactStatus::Read => 'heroicon-o-eye',
                                                ContactStatus::Resolved => 'heroicon-o-check-circle',
                                            })
                                            ->formatStateUsing(fn (ContactStatus $state): string => ucfirst($state->value)),
                                    ]),

                                Section::make('Sender details')
                                    ->icon('heroicon-o-user')
                                    ->schema([
                                        TextEntry::make('name')
                                            ->label('Name')
                                            ->placeholder('—'),
                                        TextEntry::make('email')
                                            ->label('Email')
                                            ->copyable(),
                                    ]),

                                Section::make('Record')
                                    ->icon('heroicon-o-clock')
                                    ->schema([
                                        TextEntry::make('created_at')
                                            ->label('Received')
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
