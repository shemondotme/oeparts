<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RoleResource\Pages;
use App\Filament\Support\AdminUi;
use Filament\Forms;
use Filament\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Spatie\Permission\Models\Role;
use Filament\Support\Enums\FontWeight;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-key';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'System';
    }

    protected static ?int $navigationSort = 10;

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'info';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Role Details')
                    ->icon('heroicon-o-key')
                    ->description('Define the role name and assign permissions for this access level.')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Role Name')
                            ->placeholder('e.g. Content Manager, Order Processor')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->helperText('A descriptive name for this role (e.g. "Order Manager" or "Content Editor").'),
                        Forms\Components\Select::make('guard_name')
                            ->label('Authentication Guard')
                            ->options([
                                'admin' => 'Admin Panel',
                            ])
                            ->default('admin')
                            ->native(false)
                            ->required()
                            ->disabled()
                            ->dehydrated()
                            ->helperText('The authentication guard this role applies to.'),
                        Forms\Components\Select::make('permissions')
                            ->label('Assigned Permissions')
                            ->relationship('permissions', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->columnSpanFull()
                            ->helperText('Select the permissions this role grants. Hold Ctrl/Cmd to select multiple.'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return AdminUi::configureTable($table)
            ->columns([
            Tables\Columns\TextColumn::make('name')
                ->label('Role')
                ->searchable()
                ->sortable()
                ->badge()
                ->color('warning')
                ->weight(FontWeight::Medium),
                Tables\Columns\TextColumn::make('guard_name')
                    ->label('Guard')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('permissions_count')
                    ->label('Assigned Permissions')
                    ->counts('permissions')
                    ->badge()
                    ->color('info')
                    ->fontMono()
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M j, Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('guard_name')
                    ->label('Authentication Guard')
                    ->options(['admin' => 'Admin Panel'])
                    ->default('admin')
                    ->native(false)
                    ->helperText('Filter roles by their authentication guard.'),
            ])            ->actions(AdminUi::recordActions())
        ->bulkActions([
            Actions\BulkActionGroup::make([
                AdminUi::exportCsvBulkAction('Export Roles', [
                    'name' => 'Role',
                    'guard_name' => 'Guard',
                    'permissions_count' => 'Permissions',
                    'created_at' => 'Created',
                ]),
                Actions\DeleteBulkAction::make(),
            ]),
        ])
            ->defaultSort('name', 'asc')
            ->emptyStateIcon('heroicon-o-key')
            ->emptyStateHeading('No roles configured yet')
            ->emptyStateDescription('Create roles to define permission groups for administrators. Each role can have multiple permissions.')
            ->emptyStateActions([
                Tables\Actions\Action::make('create')
                    ->label('Create Role')
                    ->url(static::getUrl('create'))
                    ->icon('heroicon-o-plus')
                    ->button(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'view' => Pages\ViewRole::route('/{record}'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name'];
    }
}

