<?php

namespace App\Filament\Resources;

use App\Enums\PartInquiryStatus;
use App\Filament\Resources\PartInquiryResource\Pages;
use App\Models\PartInquiry;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Actions;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
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
        return 'Customers';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Inquiry Details')
                    ->schema([
                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->readOnly(),
                        Forms\Components\TextInput::make('phone')
                            ->label('Phone')
                            ->readOnly(),
                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options(PartInquiryStatus::class)
                            ->required(),
                        Forms\Components\TextInput::make('oem_number')
                            ->label('OEM Number')
                            ->readOnly(),
                    ])->columns(2),

                Section::make('Vehicle Information')
                    ->schema([
                        Forms\Components\TextInput::make('manufacturer')
                            ->label('Manufacturer')
                            ->readOnly(),
                        Forms\Components\TextInput::make('car_model')
                            ->label('Car Model')
                            ->readOnly(),
                        Forms\Components\TextInput::make('year')
                            ->label('Year')
                            ->readOnly(),
                        Forms\Components\TextInput::make('vin_number')
                            ->label('VIN Number')
                            ->readOnly(),
                        Forms\Components\TextInput::make('quantity')
                            ->label('Quantity')
                            ->readOnly(),
                        Forms\Components\Select::make('urgency')
                            ->label('Urgency')
                            ->options([
                                'low' => 'Low',
                                'medium' => 'Medium',
                                'high' => 'High',
                            ])
                            ->readOnly(),
                    ])->columns(3),

                Section::make('Notes')
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->label('Customer Notes')
                            ->rows(4)
                            ->readOnly()
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('admin_note')
                            ->label('Admin Note')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('oem_number')
                    ->searchable()
                    ->extraAttributes(['class' => 'oem-number']),
                Tables\Columns\TextColumn::make('manufacturer')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (PartInquiryStatus $state): string => match ($state) {
                        PartInquiryStatus::New        => 'gray',
                        PartInquiryStatus::Reviewing  => 'warning',
                        PartInquiryStatus::Sourced    => 'success',
                        PartInquiryStatus::Unavailable => 'danger',
                    }),
                Tables\Columns\TextColumn::make('urgency')
                    ->badge()
                    ->colors([
                        'danger' => 'high',
                        'warning' => 'medium',
                        'success' => 'low',
                    ]),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(PartInquiryStatus::class),
                Tables\Filters\SelectFilter::make('urgency')
                    ->options([
                        'low' => 'Low',
                        'medium' => 'Medium',
                        'high' => 'High',
                    ]),
            ])
            ->actions([
                Actions\ViewAction::make(),
                Actions\Action::make('mark_sourced')
                    ->label('Mark Sourced')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (PartInquiry $record) {
                        $record->update(['status' => PartInquiryStatus::Sourced]);

                        Notification::make()
                            ->title('Marked as sourced')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (PartInquiry $record) => $record->status !== PartInquiryStatus::Sourced),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50, 100]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPartInquiries::route('/'),
            'view' => Pages\ViewPartInquiry::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::where('status', PartInquiryStatus::New)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}
