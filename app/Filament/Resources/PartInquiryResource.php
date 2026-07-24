<?php

namespace App\Filament\Resources;

use App\Enums\PartInquiryStatus;
use App\Filament\Resources\PartInquiryResource\Pages;
use App\Filament\Support\AdminUi;
use App\Models\PartInquiry;
use Filament\Actions;
use Filament\Actions\BulkAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;

class PartInquiryResource extends Resource
{
    protected static ?string $model = PartInquiry::class;

    protected static ?int $navigationSort = 20;

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-chat-bubble-left-right';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Catalog';
    }

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
                                Section::make('Inquiry Details')
                                    ->icon('heroicon-o-chat-bubble-left-right')
                                    ->description('Core information for this part inquiry.')
                                    ->schema([
                                        Forms\Components\TextInput::make('oem_number')
                                            ->label(__('admin.oem_part_number'))
                                            ->readOnly()
                                            ->extraAttributes(['class' => 'font-mono uppercase']),
                                        Forms\Components\TextInput::make('quantity')
                                            ->label(__('admin.requested_quantity'))
                                            ->numeric()
                                            ->readOnly()
                                            ->helperText('Number of units the customer needs.'),
                                        Forms\Components\Select::make('urgency')
                                            ->label(__('admin.urgency_level'))
                                            ->options([
                                                'normal' => 'Normal',
                                                'soon' => 'Soon (within a week)',
                                                'urgent' => 'Urgent (ASAP)',
                                            ])
                                            ->disabled()
                                            ->helperText('Customer-specified delivery urgency.'),
                                    ])
                                    ->columns(3),

                                Section::make('Vehicle Information')
                                    ->icon('heroicon-o-wrench-screwdriver')
                                    ->description('Vehicle details provided by the customer for part compatibility.')
                                    ->schema([
                                        Forms\Components\TextInput::make('manufacturer')
                                            ->label(__('admin.vehicle_manufacturer'))
                                            ->readOnly(),
                                        Forms\Components\TextInput::make('car_model')
                                            ->label(__('admin.car_model'))
                                            ->readOnly(),
                                        Forms\Components\TextInput::make('year')
                                            ->label(__('admin.model_year'))
                                            ->readOnly(),
                                        Forms\Components\TextInput::make('vin_number')
                                            ->label(__('admin.vin_number'))
                                            ->readOnly()
                                            ->extraAttributes(['class' => 'font-mono uppercase']),
                                    ])
                                    ->columns(2),

                                Section::make('Customer Notes')
                                    ->icon('heroicon-o-document-text')
                                    ->description('Additional details or special requirements from the customer.')
                                    ->schema([
                                        Forms\Components\Textarea::make('notes')
                                            ->hiddenLabel()
                                            ->rows(4)
                                            ->readOnly()
                                            ->placeholder('No additional notes provided by the customer.')
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        // ─── Sidebar column ───────────────────────────────
                        Group::make()
                            ->columnSpan(['default' => 1, 'xl' => 1])
                            ->schema([
                                Section::make('Status & Processing')
                                    ->icon('heroicon-o-adjustments-horizontal')
                                    ->description('Track and manage this inquiry through the sourcing workflow.')
                                    ->schema([
                                        Forms\Components\Select::make('status')
                                            ->label(__('admin.inquiry_status'))
                                            ->options(PartInquiryStatus::class)
                                            ->native(false)
                                            ->required()
                                            ->helperText('Current processing state of this part inquiry.'),
                                        Forms\Components\Textarea::make('admin_note')
                                            ->label(__('admin.internal_admin_note'))
                                            ->rows(4)
                                            ->placeholder('e.g. Part sourced from Supplier X, ETA 3 days...')
                                            ->helperText('Private notes for tracking. Not visible to the customer.')
                                            ->nullable(),
                                    ]),

                                Section::make('Customer Contact')
                                    ->icon('heroicon-o-user')
                                    ->description('Contact details for the customer who submitted this inquiry.')
                                    ->schema([
                                        Forms\Components\TextInput::make('email')
                                            ->label(__('admin.email_address'))
                                            ->email()
                                            ->readOnly(),
                                        Forms\Components\TextInput::make('phone')
                                            ->label(__('admin.phone_number'))
                                            ->tel()
                                            ->readOnly()
                                            ->placeholder('Not provided'),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return AdminUi::configureTable($table)
            ->columns([
                AdminUi::copyableColumn('email', 'Email', 'Email copied')
                    ->searchable(),
                AdminUi::oemColumn('oem_number', 'OEM number copied'),
                Tables\Columns\TextColumn::make('manufacturer')
                    ->label(__('admin.manufacturer'))
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('admin.status'))
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
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('urgency')
                    ->label(__('admin.urgency'))
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
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('admin.date'))
                    ->dateTime('M j, Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('admin.inquiry_status'))
                    ->options(PartInquiryStatus::class)
                    ->native(false)
                    ->helperText('Filter by processing status.')
                    ->columnSpan(1),
                Tables\Filters\SelectFilter::make('urgency')
                    ->label(__('admin.urgency_level'))
                    ->options([
                        'normal' => 'Normal',
                        'soon' => 'Soon',
                        'urgent' => 'Urgent',
                    ])
                    ->native(false)
                    ->helperText('Filter by customer-specified urgency.')
                    ->columnSpan(1),
                Tables\Filters\Filter::make('created_at')
                    ->label(__('admin.inquiry_date'))
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label(__('admin.submitted_after'))
                            ->placeholder('Select start date'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label(__('admin.submitted_before'))
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
                static::makeMarkSourcedAction(),
                static::makeMarkUnavailableAction(),
            ]))
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    AdminUi::impactBulkAction(
                        name: 'bulkMarkSourced',
                        label: 'Mark Sourced',
                        color: 'success',
                        icon: 'heroicon-o-check-circle',
                        summary: fn ($record): ?array => $record->status === PartInquiryStatus::Sourced
                            ? null
                            : [
                                'key' => $record->oem_number,
                                'old' => $record->status->value,
                                'new' => PartInquiryStatus::Sourced->value,
                            ],
                        visible: fn ($records): bool => $records->contains(fn ($r) => $r->status !== PartInquiryStatus::Sourced),
                        action: function ($records) {
                            $count = 0;
                            $records->each(function (PartInquiry $record) use (&$count) {
                                if ($record->status !== PartInquiryStatus::Sourced) {
                                    static::transitionAndNotify($record, PartInquiryStatus::Sourced);
                                    $count++;
                                }
                            });

                            Notification::make()
                                ->title("{$count} " . str('inquiry')->plural($count) . ' marked as sourced')
                                ->body('Customers with an email on file have been notified.')
                                ->success()
                                ->send();
                        },
                    )->authorize('update', PartInquiry::class),
                    AdminUi::impactBulkAction(
                        name: 'bulkMarkUnavailable',
                        label: 'Mark Unavailable',
                        color: 'danger',
                        icon: 'heroicon-o-x-circle',
                        summary: fn ($record): ?array => $record->status === PartInquiryStatus::Unavailable
                            ? null
                            : [
                                'key' => $record->oem_number,
                                'old' => $record->status->value,
                                'new' => PartInquiryStatus::Unavailable->value,
                            ],
                        action: function ($records) {
                            $count = 0;
                            $records->each(function (PartInquiry $record) use (&$count) {
                                if ($record->status !== PartInquiryStatus::Unavailable) {
                                    static::transitionAndNotify($record, PartInquiryStatus::Unavailable);
                                    $count++;
                                }
                            });

                            Notification::make()
                                ->title("{$count} " . str('inquiry')->plural($count) . ' marked as unavailable')
                                ->body('Customers with an email on file have been notified.')
                                ->success()
                                ->send();
                        },
                    )->authorize('update', PartInquiry::class),
                    AdminUi::exportCsvBulkAction('Export Part Inquiries', [
                        'email' => 'Email',
                        'oem_number' => 'OEM Number',
                        'manufacturer' => 'Manufacturer',
                        'car_model' => 'Car Model',
                        'status' => 'Status',
                        'urgency' => 'Urgency',
                        'created_at' => 'Date',
                    ]),
                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateIcon('heroicon-o-chat-bubble-left-right')
            ->emptyStateHeading('No part inquiries')
            ->emptyStateDescription('Customer part requests will appear here when submitted through the storefront inquiry form.');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPartInquiries::route('/'),
            'create' => Pages\CreatePartInquiry::route('/create'),
            'view' => Pages\ViewPartInquiry::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return \App\Support\NavBadge::count('inquiries_new', fn () => static::getModel()::where('status', PartInquiryStatus::New)->count());
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'New part inquiries';
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['oem_number', 'email', 'manufacturer'];
    }

    /**
     * Transition an inquiry and email the customer (when one is on file) —
     * the storefront inquiry form promises a concierge response, so status
     * verdicts must close that loop.
     */
    public static function transitionAndNotify(PartInquiry $record, PartInquiryStatus $status): void
    {
        $record->update(['status' => $status]);

        if (filled($record->email)) {
            dispatch(new \App\Jobs\SendPartInquiryStatusEmail($record, $status));
        }
    }

    public static function makeMarkSourcedAction(): Actions\Action
    {
        return Actions\Action::make('mark_sourced')
            ->label(__('admin.mark_sourced'))
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->authorize('update')
            ->requiresConfirmation()
            ->modalHeading('Mark Inquiry as Sourced')
            ->modalDescription('Confirm the requested part has been found. The customer receives a "part sourced" email; follow up with pricing and delivery.')
            ->action(function (PartInquiry $record): void {
                static::transitionAndNotify($record, PartInquiryStatus::Sourced);

                Notification::make()
                    ->title('Inquiry marked as sourced')
                    ->body(filled($record->email) ? 'The customer has been emailed.' : 'No customer email on file — no notification sent.')
                    ->success()
                    ->send();
            })
            ->visible(fn (PartInquiry $record) => $record->status !== PartInquiryStatus::Sourced);
    }

    public static function makeMarkUnavailableAction(): Actions\Action
    {
        return Actions\Action::make('markUnavailable')
            ->label(__('admin.mark_unavailable'))
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->authorize('update')
            ->requiresConfirmation()
            ->modalHeading('Mark Inquiry as Unavailable')
            ->modalDescription('Confirm the requested part cannot be sourced. The customer receives an "unavailable" email so they are not left waiting.')
            ->action(function (PartInquiry $record): void {
                static::transitionAndNotify($record, PartInquiryStatus::Unavailable);

                Notification::make()
                    ->title('Inquiry marked as unavailable')
                    ->body(filled($record->email) ? 'The customer has been emailed.' : 'No customer email on file — no notification sent.')
                    ->success()
                    ->send();
            })
            ->visible(fn (PartInquiry $record) => $record->status !== PartInquiryStatus::Unavailable);
    }
}
