<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AdminResource\Pages;
use App\Filament\Resources\AdminResource\RelationManagers;
use App\Filament\Support\AdminUi;
use App\Models\Admin;
use Filament\Forms;
use Filament\Actions;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;
use Filament\Support\Enums\FontWeight;

class AdminResource extends Resource
{
    protected static ?string $model = Admin::class;

    protected static ?string $recordTitleAttribute = 'email';

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-shield-check';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'System';
    }

    protected static ?int $navigationSort = 20;

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::where('is_active', false)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
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
                                Section::make('Admin Details')
                                    ->icon('heroicon-o-user')
                                    ->description('Account information and role assignment for this administrator.')
                                    ->schema([
                                        Forms\Components\TextInput::make('name')
                                            ->label('Full Name')
                                            ->placeholder('e.g. Jan de Vries')
                                            ->required()
                                            ->maxLength(200),
                                        Forms\Components\TextInput::make('email')
                                            ->label('Email Address')
                                            ->email()
                                            ->required()
                                            ->unique(ignoreRecord: true)
                                            ->maxLength(255)
                                            ->helperText('Used for login and notifications.'),
                                        Forms\Components\TextInput::make('password')
                                            ->label('Password')
                                            ->password()
                                            ->revealable()
                                            ->dehydrateStateUsing(fn (string $state): string => Hash::make($state))
                                            ->dehydrated(fn (?string $state): bool => filled($state))
                                            ->required(fn (string $operation): bool => $operation === 'create')
                                            ->minLength(8)
                                            ->rules(['regex:/^(?=.*[A-Z])(?=.*\d).+$/'])
                                            ->placeholder(fn (string $operation): string => $operation === 'create' ? 'Enter a strong password' : 'Leave empty to keep current password')
                                            ->helperText('Minimum 8 characters with at least one uppercase letter and one number.'),
                                        Forms\Components\Select::make('roles')
                                            ->label('Assigned Roles')
                                            ->relationship('roles', 'name')
                                            ->multiple()
                                            ->preload()
                                            ->native(false)
                                            ->required()
                                            ->helperText('Select one or more roles to grant permissions.'),
                                    ])->columns(2),
                            ]),

                        // ─── Sidebar column ───────────────────────────────
                        Group::make()
                            ->columnSpan(['default' => 1, 'xl' => 1])
                            ->schema([
                                Section::make('Settings')
                                    ->icon('heroicon-o-adjustments-horizontal')
                                    ->description('Account status and active toggle.')
                                    ->schema([
                                        Forms\Components\Toggle::make('is_active')
                                            ->label('Account Active')
                                            ->helperText('Deactivated admins cannot log in to the panel.')
                                            ->default(true),
                                    ]),
                                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return AdminUi::configureTable($table)
            ->modifyQueryUsing(fn ($query) => $query->with('roles'))
            ->columns([
            Tables\Columns\TextColumn::make('name')
                ->label('Name')
                ->searchable()
                ->sortable()
                ->weight(FontWeight::Medium),
            Tables\Columns\TextColumn::make('email')
                ->label('Email Address')
                ->searchable()
                ->sortable()
                ->copyable()
                ->copyMessage('Email copied')
                ->limit(30),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('last_login_at')
                    ->label('Last Login')
                    ->dateTime('M j, Y H:i')
                    ->sortable()
                    ->placeholder('Never logged in'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M j, Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Account Status')
                    ->placeholder('All')
                    ->trueLabel('Active Only')
                    ->falseLabel('Inactive Only')
                    ->native(false),
            ])
            ->actions(AdminUi::recordActions())
        ->bulkActions([
            Actions\BulkActionGroup::make([
                AdminUi::exportCsvBulkAction('Export Admins', [
                    'name' => 'Name',
                    'email' => 'Email',
                    'is_active' => 'Active',
                    'last_login_at' => 'Last Login',
                    'created_at' => 'Created',
                ]),
                Actions\DeleteBulkAction::make(),
            ]),
        ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateIcon('heroicon-o-shield-check')
            ->emptyStateHeading('No administrators created yet')
            ->emptyStateDescription('Create admin accounts with appropriate roles to manage the panel and its features.')
            ->emptyStateActions([
                Tables\Actions\Action::make('create')
                    ->label('Add Admin')
                    ->url(static::getUrl('create'))
                    ->icon('heroicon-o-plus')
                    ->button(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ActivityLogsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAdmins::route('/'),
            'create' => Pages\CreateAdmin::route('/create'),
            'view' => Pages\ViewAdmin::route('/{record}'),
            'edit' => Pages\EditAdmin::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email'];
    }
}

