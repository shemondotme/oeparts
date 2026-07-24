<?php

namespace App\Filament\Resources;

use App\Enums\ContactStatus;
use App\Enums\ContactSubjectType;
use App\Filament\Resources\ContactMessageResource\Pages;
use App\Filament\Support\AdminUi;
use App\Jobs\SendContactReplyEmail;
use App\Models\ContactMessage;
use Filament\Forms;
use Filament\Actions;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;

class ContactMessageResource extends Resource
{
    protected static ?string $model = ContactMessage::class;

    protected static ?string $recordTitleAttribute = 'subject';

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-chat-bubble-left-ellipsis';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Customers';
    }

    protected static ?int $navigationSort = 30;

    // No form(): this resource is read-only (index + view pages only) —
    // status changes go through the reply/mark actions.

    public static function table(Table $table): Table
    {
        return AdminUi::configureTable($table)
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('admin.name'))
                    ->searchable()
                    ->sortable()
                    // Unread messages read bolder — the empty-state copy
                    // promises highlighting, so deliver it.
                    ->weight(fn (ContactMessage $record): FontWeight => $record->status === ContactStatus::Unread
                        ? FontWeight::Bold
                        : FontWeight::Medium)
                    ->icon(fn (ContactMessage $record): ?string => $record->status === ContactStatus::Unread
                        ? 'heroicon-s-envelope'
                        : null)
                    ->iconColor('warning')
                    ->limit(25),
                Tables\Columns\TextColumn::make('email')
                    ->label(__('admin.email'))
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Email copied'),
                Tables\Columns\TextColumn::make('subject_type')
                    ->label(__('admin.subject'))
                    ->badge()
                    ->color(fn (ContactSubjectType $state): string => match ($state) {
                        ContactSubjectType::GeneralInquiry => 'gray',
                        ContactSubjectType::OrderIssue => 'info',
                        ContactSubjectType::ReturnRefund => 'warning',
                        ContactSubjectType::B2bPartnership => 'success',
                        default => 'gray',
                    })
                    ->icon(fn (ContactSubjectType $state): string => match ($state) {
                        ContactSubjectType::GeneralInquiry => 'heroicon-o-question-mark-circle',
                        ContactSubjectType::PartNotFound => 'heroicon-o-x-circle',
                        ContactSubjectType::OrderIssue => 'heroicon-o-shopping-cart',
                        ContactSubjectType::ShippingQuestion => 'heroicon-o-truck',
                        ContactSubjectType::ReturnRefund => 'heroicon-o-arrow-path',
                        ContactSubjectType::B2bPartnership => 'heroicon-o-building-office-2',
                        ContactSubjectType::Other => 'heroicon-o-ellipsis-horizontal-circle',
                    })
                    ->searchable(),
                Tables\Columns\TextColumn::make('message')
                    ->label(__('admin.preview'))
                    ->limit(50)
                    ->tooltip(fn (ContactMessage $record): string => $record->message)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('admin.status'))
                    ->badge()
                    ->color(fn (ContactStatus $state): string => match ($state) {
                        ContactStatus::Unread => 'warning',
                        ContactStatus::Read => 'info',
                        ContactStatus::Resolved => 'success',
                    })
                    ->icon(fn (ContactStatus $state): string => match ($state) {
                        ContactStatus::Unread => 'heroicon-o-envelope',
                        ContactStatus::Read => 'heroicon-o-eye',
                        ContactStatus::Resolved => 'heroicon-o-check-circle',
                    })
                    ->formatStateUsing(fn (ContactStatus $state): string => ucfirst($state->value))
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('admin.received'))
                    ->dateTime('M j, Y H:i')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateIcon('heroicon-o-chat-bubble-left-ellipsis')
            ->emptyStateHeading('No contact messages')
            ->emptyStateDescription('Contact form submissions from customers will appear here. Unread messages will be highlighted.')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('admin.message_status'))
                    ->options(ContactStatus::class)
                    ->native(false)
                    ->helperText('Filter by message handling status.')
                    ->columnSpan(1),
                Tables\Filters\Filter::make('created_at')
                    ->label(__('admin.received_date'))
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label(__('admin.received_after'))
                            ->placeholder('Select start date'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label(__('admin.received_before'))
                            ->placeholder('Select end date'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['created_from'], fn ($q) => $q->whereDate('created_at', '>=', $data['created_from']))
                            ->when($data['created_until'], fn ($q) => $q->whereDate('created_at', '<=', $data['created_until']));
                    })
                    ->columnSpan(2),
            ])
            ->filtersFormColumns(2)
            ->actions(AdminUi::recordActions(after: [
                Actions\Action::make('reply')
                    ->label(__('admin.reply'))
                    // 'reply' was removed in Heroicons v2 — the missing SVG
                    // 500'd the entire deferred table render.
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('info')
                    ->authorize('update')
                    ->requiresConfirmation()
                    ->modalHeading('Reply to Contact Message')
                    ->modalDescription(fn (ContactMessage $record): string => "Send a reply to {$record->email}. The reply is saved on the message for future reference.")
                    ->schema([
                        Forms\Components\Textarea::make('reply_body')
                            ->label(__('admin.reply_message'))
                            ->required()
                            ->rows(6)
                            ->placeholder('Type your reply to the customer...')
                            ->helperText('Sent via email (queued) and stored on this message.'),
                        Forms\Components\Toggle::make('mark_resolved')
                            ->label(__('admin.mark_as_resolved'))
                            ->default(true)
                            ->helperText('Disable if you expect further back-and-forth on this message.'),
                    ])
                    ->action(function (ContactMessage $record, array $data): void {
                        dispatch(new SendContactReplyEmail($record, $data['reply_body']));

                        $record->update([
                            'reply_body' => $data['reply_body'],
                            'replied_at' => now(),
                            'replied_by' => auth('admin')->id(),
                            'status'     => ($data['mark_resolved'] ?? true) ? ContactStatus::Resolved : ContactStatus::Read,
                        ]);

                        Notification::make()
                            ->title('Reply queued')
                            ->body("Reply to {$record->email} has been queued and saved.")
                            ->success()
                            ->send();
                    }),
                Actions\Action::make('markRead')
                    ->label(__('admin.mark_read'))
                    ->icon('heroicon-o-eye')
                    ->color('warning')
                    ->authorize('update')
                    ->action(function (ContactMessage $record) {
                        $record->update(['status' => 'read']);
                        
                        Notification::make()
                            ->title('Message marked as read')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (ContactMessage $record): bool => $record->status === ContactStatus::Unread),
                Actions\Action::make('markResolved')
                    ->label(__('admin.resolve'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->authorize('update')
                    ->action(function (ContactMessage $record) {
                        $record->update(['status' => 'resolved']);
                        
                        Notification::make()
                            ->title('Message marked as resolved')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (ContactMessage $record): bool => $record->status !== ContactStatus::Resolved),
            ]))
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    AdminUi::impactBulkAction(
                        name: 'bulkMarkRead',
                        label: 'Mark Read',
                        color: 'warning',
                        icon: 'heroicon-o-eye',
                        summary: fn ($record): ?array => $record->status !== ContactStatus::Unread
                            ? null
                            : [
                                'key' => $record->email,
                                'old' => $record->status->value,
                                'new' => 'read',
                            ],
                        visible: fn ($records): bool => $records->contains(fn ($r) => $r->status === ContactStatus::Unread),
                        action: function ($records) {
                            $count = 0;
                            $records->each(function (ContactMessage $record) use (&$count) {
                                if ($record->status === ContactStatus::Unread) {
                                    $record->update(['status' => 'read']);
                                    $count++;
                                }
                            });

                            Notification::make()
                                ->title("{$count} messages marked as read")
                                ->success()
                                ->send();
                        },
                    )->authorize('update', ContactMessage::class),
                    AdminUi::impactBulkAction(
                        name: 'bulkMarkResolved',
                        label: 'Mark Resolved',
                        color: 'success',
                        icon: 'heroicon-o-check-circle',
                        summary: fn ($record): ?array => $record->status === 'resolved'
                            ? null
                            : [
                                'key' => $record->email,
                                'old' => $record->status->value,
                                'new' => 'resolved',
                            ],
                        action: function ($records) {
                            $records->each(function (ContactMessage $record) {
                                $record->update(['status' => 'resolved']);
                            });

                            Notification::make()
                                ->title($records->count() . ' messages marked as resolved')
                                ->success()
                                ->send();
                        },
                    )->authorize('update', ContactMessage::class),
                    AdminUi::exportCsvBulkAction('Export Contact Messages', [
                        'name' => 'Name',
                        'email' => 'Email',
                        'subject_type' => 'Subject',
                        'status' => 'Status',
                        'created_at' => 'Date',
                    ]),
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getNavigationBadge(): ?string
    {
        return \App\Support\NavBadge::count('messages_unread', fn () => static::getModel()::where('status', 'unread')->count());
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Unread messages';
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContactMessages::route('/'),
            'view' => Pages\ViewContactMessage::route('/{record}'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email', 'subject_type', 'message'];
    }
}
