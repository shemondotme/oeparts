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
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
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

    public static function form(Schema $schema): Schema
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
                                    ->description('Content of the contact message sent by the customer.')
                                    ->schema([
                                        Forms\Components\TextInput::make('subject_type')
                                            ->label('Subject / Inquiry Type')
                                            ->readOnly()
                                            ->helperText('The category of this contact message.')
                                            ->columnSpanFull(),
                                        Forms\Components\Textarea::make('message')
                                            ->label('Message Body')
                                            ->rows(6)
                                            ->readOnly()
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        // ─── Sidebar column ───────────────────────────────
                        Group::make()
                            ->columnSpan(['default' => 1, 'xl' => 1])
                            ->schema([
                                Section::make('Status & Processing')
                                    ->icon('heroicon-o-adjustments-horizontal')
                                    ->description('Track message handling and resolution status.')
                                    ->schema([
                                        Forms\Components\Select::make('status')
                                            ->label('Message Status')
                                            ->options([
                                                'unread' => 'Unread',
                                                'read' => 'Read',
                                                'resolved' => 'Resolved',
                                            ])
                                            ->native(false)
                                            ->required()
                                            ->helperText('Current handling state of this contact message.'),
                                    ]),

                                Section::make('Sender Details')
                                    ->icon('heroicon-o-user')
                                    ->description('Contact information for the person who sent this message.')
                                    ->schema([
                                        Forms\Components\TextInput::make('name')
                                            ->label('Sender Name')
                                            ->readOnly(),
                                        Forms\Components\TextInput::make('email')
                                            ->label('Email Address')
                                            ->email()
                                            ->readOnly(),
                                    ]),

                                Section::make('Part / Order Reference')
                                    ->icon('heroicon-o-magnifying-glass')
                                    ->description('Related order or part details if provided by the customer.')
                                    ->collapsible()
                                    ->schema([
                                        Forms\Components\TextInput::make('order_number')
                                            ->label('Order Number')
                                            ->readOnly()
                                            ->helperText('Related order if the customer referenced one.'),
                                        Forms\Components\TextInput::make('oem_number')
                                            ->label('OEM Part Number')
                                            ->readOnly()
                                            ->extraAttributes(['class' => 'font-mono uppercase']),
                                        Forms\Components\TextInput::make('manufacturer')
                                            ->label('Vehicle Manufacturer')
                                            ->readOnly(),
                                        Forms\Components\TextInput::make('car_model')
                                            ->label('Car Model')
                                            ->readOnly(),
                                        Forms\Components\TextInput::make('year')
                                            ->label('Model Year')
                                            ->readOnly(),
                                        Forms\Components\TextInput::make('vin_number')
                                            ->label('VIN Number')
                                            ->readOnly(),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return AdminUi::configureTable($table)
            ->modifyQueryUsing(fn ($query) => $query->with('sender'))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Medium)
                    ->limit(25),
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Email copied'),
                Tables\Columns\TextColumn::make('subject_type')
                    ->label('Subject')
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
                    ->label('Preview')
                    ->limit(50)
                    ->tooltip(fn (ContactMessage $record): string => $record->message)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
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
                    ->label('Received')
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
                    ->label('Message Status')
                    ->options([
                        'unread' => 'Unread',
                        'read' => 'Read',
                        'resolved' => 'Resolved',
                    ])
                    ->native(false)
                    ->helperText('Filter by message handling status.')
                    ->columnSpan(1),
                Tables\Filters\Filter::make('created_at')
                    ->label('Received Date')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('Received After')
                            ->placeholder('Select start date'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('Received Before')
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
                    ->label('Reply')
                    ->icon('heroicon-o-reply')
                    ->color('info')
                    ->authorize('update')
                    ->requiresConfirmation()
                    ->modalHeading('Reply to Contact Message')
                    ->modalDescription(fn (ContactMessage $record): string => "Send a reply to {$record->email}. The message status will be updated to 'Read'.")
                    ->schema([
                        Forms\Components\Textarea::make('reply_body')
                            ->label('Reply Message')
                            ->required()
                            ->rows(6)
                            ->placeholder('Type your reply to the customer...')
                            ->helperText('Your reply will be sent via email and queued as a background job.'),
                    ])
                    ->action(function (ContactMessage $record, array $data): void {
                        dispatch(new SendContactReplyEmail($record, $data['reply_body']));

                        $record->update(['status' => 'read']);

                        Notification::make()
                            ->title('Reply queued')
                            ->body("Reply to {$record->email} has been queued.")
                            ->success()
                            ->send();
                    }),
                Actions\Action::make('markRead')
                    ->label('Mark Read')
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
                    ->label('Resolve')
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
                    ),
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
                    ),
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
        $count = static::getModel()::where('status', 'unread')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
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
