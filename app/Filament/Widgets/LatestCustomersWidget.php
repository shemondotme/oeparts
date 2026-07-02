<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\CustomerResource;
use App\Models\User;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class LatestCustomersWidget extends TableWidget
{
    use Concerns\HasWidgetRoles;
    use Concerns\InteractsWithDashboardCache;

    public function getDescription(): ?string
    {
        return 'Most recent customer registrations';
    }

    protected ?string $pollingInterval = '60s';

    protected static ?int $sort = -12;

    protected static ?string $heading = 'Latest Customers';

    protected int | string | array $columnSpan = ['md' => 1, 'xl' => 1];

    public function table(Table $table): Table
    {
        return $table
            ->query(
                User::query()
                    ->latest()
                    ->limit(10)
            )
            ->columns([
                IconColumn::make('is_active')
                    ->label('')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('gray'),
                TextColumn::make('name')
                    ->label('Name')
                    ->weight(FontWeight::Bold)
                    ->searchable()
                    ->limit(26)
                    ->tooltip(fn (User $record): ?string => $record->email)
                    ->description(fn (User $record): string => \Illuminate\Support\Str::limit((string) $record->email, 32))
                    ->copyable()
                    ->copyableState(fn (User $record): string => (string) $record->email)
                    ->copyMessage('Email copied'),
                TextColumn::make('created_at')
                    ->label('Joined')
                    ->since()
                    ->color('gray')
                    ->description('joined')
                    ->alignEnd(),
            ])
            ->recordUrl(fn (User $record): string => CustomerResource::getUrl('view', ['record' => $record]))
            ->striped()
            ->emptyStateIcon('heroicon-o-user-group')
            ->emptyStateHeading('No customers yet')
            ->emptyStateDescription('New customer registrations will appear here.')
            ->searchable(false)
            ->paginated(false);
    }
}
