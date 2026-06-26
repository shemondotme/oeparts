<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\HasWidgetExport;
use App\Filament\Resources\CustomerResource;
use App\Models\User;
use Filament\Actions\ViewAction;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class LatestCustomersWidget extends TableWidget
{
    use Concerns\HasDashboardPeriod;
    use Concerns\HasWidgetRoles;
    use Concerns\InteractsWithDashboardCache;
    use HasWidgetExport;

    public function getDescription(): ?string
    {
        return 'Customers who registered in the selected period';
    }

    protected ?string $pollingInterval = '60s';

    protected static ?int $sort = -12;

    protected static ?string $heading = 'Latest Customers';

    protected int | string | array $columnSpan = ['md' => 1, 'xl' => 1];

    protected function getExportHeaders(): array
    {
        return ['Name', 'Email', 'Joined', 'Status'];
    }

    protected function getExportRows(): iterable
    {
        return User::where('created_at', '>=', $this->periodStart())
            ->latest()
            ->get()
            ->map(fn (User $u) => [
                $u->name,
                $u->email,
                $u->created_at?->format('d M Y H:i') ?? '—',
                $u->is_active ? 'Active' : 'Inactive',
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                User::where('created_at', '>=', $this->periodStart())
                    ->latest()
                    ->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->limit(22),
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->limit(24)
                    ->copyable()
                    ->copyMessage('Email copied'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Joined')
                    ->since()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('gray'),
            ])
            ->actions([
                ViewAction::make()
                    ->url(fn (User $record): string => CustomerResource::getUrl('view', ['record' => $record])),
            ])
            ->emptyState(
                view('filament.widgets.empty-state', [
                    'icon' => 'heroicon-o-users',
                    'heading' => 'No new customers',
                    'description' => 'No customers signed up in the selected period.',
                    'ctaLabel' => 'View all customers',
                    'ctaUrl' => CustomerResource::getUrl('index'),
                ])
            )
            ->emptyStateDescription('')
            ->searchable(false)
            ->paginated(false);
    }
}
