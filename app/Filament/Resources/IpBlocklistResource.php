<?php

namespace App\Filament\Resources;

use App\Filament\Resources\IpBlocklistResource\Pages;
use App\Filament\Support\AdminUi;
use App\Models\IpBlocklist;
use Filament\Forms;
use Filament\Actions;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Support\Enums\FontWeight;

class IpBlocklistResource extends Resource
{
    protected static ?string $model = IpBlocklist::class;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-no-symbol';
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::where('is_active', true)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'gray';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'System';
    }

    public static function getNavigationSort(): ?int
    {
        return 110;
    }

    public static function getRecordTitleAttribute(): ?string
    {
        return 'ip_address';
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
                                Section::make('Firewall Block Details')
                                    ->icon('heroicon-o-shield-exclamation')
                                    ->description('IP address to block and the reason for the block.')
                                    ->schema([
                                        Forms\Components\TextInput::make('ip_address')
                                            ->label('IP Address')
                                            ->placeholder('e.g. 192.168.1.1 or 2001:db8::1')
                                            ->required()
                                            ->maxLength(45)
                                            ->helperText('IPv4 or IPv6 address to block. Supports full addresses.'),
                                        Forms\Components\Textarea::make('reason')
                                            ->label('Reason / Notes')
                                            ->placeholder('e.g. Repeated failed login attempts, spam bot, DDoS source...')
                                            ->rows(4)
                                            ->required()
                                            ->maxLength(255)
                                            ->helperText('Document why this IP is blocked for future reference.'),
                                    ])->columns(1),
                            ]),

                        // ─── Sidebar column ───────────────────────────────
                        Group::make()
                            ->columnSpan(['default' => 1, 'xl' => 1])
                            ->schema([
                                Section::make('Expiry & Settings')
                                    ->icon('heroicon-o-adjustments-horizontal')
                                    ->description('Block status and optional expiration date.')
                                    ->schema([
                                        Forms\Components\Toggle::make('is_active')
                                            ->label('Block Active')
                                            ->helperText('Inactive blocks are not enforced.')
                                            ->default(true),
                                        Forms\Components\DateTimePicker::make('expires_at')
                                            ->label('Expiration Date')
                                            ->nullable()
                                            ->helperText('Leave empty for a permanent block. Set a date to auto-expire the block.'),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return AdminUi::configureTable($table)
            ->modifyQueryUsing(fn ($query) => $query->with('blocker'))
            ->columns([
            Tables\Columns\TextColumn::make('ip_address')
                ->label('IP Address')
                ->searchable()
                ->copyable()
                ->copyMessage('IP address copied')
                ->sortable()
                ->fontMono()
                ->weight(FontWeight::Medium),
            Tables\Columns\TextColumn::make('reason')
                ->label('Reason')
                ->limit(40)
                ->toggleable(),
                Tables\Columns\TextColumn::make('blocker.name')
                    ->label('Blocked By')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Expires')
                    ->dateTime('M j, Y H:i')
                    ->placeholder('Permanent block')
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Block Status')
                    ->placeholder('All')
                    ->trueLabel('Active Only')
                    ->falseLabel('Inactive Only')
                    ->native(false),
                Tables\Filters\Filter::make('expired')
                    ->label('Expired Blocks')
                    ->query(fn (Builder $query): Builder => $query->where('expires_at', '<', now())->whereNotNull('expires_at'))
                    ->helperText('Show only IP blocks that have expired.'),
            ])
            ->actions(AdminUi::recordActionsWithoutView())
        ->bulkActions([
            Actions\BulkActionGroup::make([
                AdminUi::exportCsvBulkAction('Export Blocked IPs', [
                    'ip_address' => 'IP Address',
                    'reason' => 'Reason',
                    'blocker.name' => 'Blocked By',
                    'is_active' => 'Active',
                    'expires_at' => 'Expires',
                ]),
                Actions\DeleteBulkAction::make(),
            ]),
        ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateIcon('heroicon-o-no-symbol')
            ->emptyStateHeading('No blocked IP addresses')
            ->emptyStateDescription('Blocked IP addresses will appear here. Use this to restrict access from malicious or unwanted sources.');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListIpBlocklists::route('/'),
            'create' => Pages\CreateIpBlocklist::route('/create'),
            'view'   => Pages\ViewIpBlocklist::route('/{record}'),
            'edit'   => Pages\EditIpBlocklist::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['ip_address', 'reason'];
    }
}
