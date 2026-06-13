<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\CustomerResource;
use App\Models\User;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class LatestCustomersWidget extends TableWidget
{
    public function getDescription(): ?string
    {
        return 'Recently registered customers';
    }

    use \App\Filament\Widgets\Concerns\HasDashboardPeriod;
    use \App\Filament\Widgets\Concerns\HasWidgetRoles;

    protected ?string $pollingInterval = '60s';

    protected static ?int $sort = -18;

    protected static ?string $heading = 'Latest Customers';

    protected int|string|array $columnSpan = ['md' => 1, 'xl' => 1];

    public function table(Table $table): Table
    {
        return $table
            ->query(
                User::query()
                    ->where('created_at', '>=', $this->periodStart())
                    ->latest()
                    ->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->limit(20)
                    ->size('sm'),
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->limit(25)
                    ->size('sm'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Joined')
                    ->since()
                    ->size('sm'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->url(fn (User $record): string => CustomerResource::getUrl('view', ['record' => $record]))
                    ->size('sm')
                    ->icon('heroicon-m-eye'),
            ])
            ->paginated(false);
    }
}
