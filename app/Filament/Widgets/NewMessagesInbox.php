<?php

namespace App\Filament\Widgets;

use App\Enums\ContactStatus;
use App\Models\ContactMessage;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class NewMessagesInbox extends TableWidget
{
    use Concerns\HasWidgetRoles;
    use Concerns\InteractsWithDashboardCache;

    protected static bool $isLazy = true;

    protected static ?string $heading = 'New Messages';

    protected ?string $pollingInterval = '60s';

    protected int | string | array $columnSpan = ['md' => 1, 'xl' => 1];

    protected static ?int $sort = -31;

    public function getDescription(): ?string
    {
        return 'Recent unread contact messages';
    }

    protected function getTableHeading(): string
    {
        $d = $this->cachedWidgetData(fn (): array => [
            'count' => ContactMessage::where('status', ContactStatus::Unread->value)->count(),
        ]);

        return 'New Messages' . ($d['count'] > 0 ? " ({$d['count']})" : '');
    }

    protected function getTableHeaderActions(): array
    {
        return [
            Tables\Actions\Action::make('view_all')
                ->label('View all')
                ->icon('heroicon-o-arrow-right')
                ->link()
                ->url(\App\Filament\Resources\ContactMessageResource::getUrl('index')),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ContactMessage::query()
                    ->latest()
                    ->limit(10)
            )
            ->columns([
                IconColumn::make('status')
                    ->label('')
                    ->icon(fn (ContactStatus $state): string => $state === ContactStatus::Unread ? 'heroicon-o-envelope' : 'heroicon-o-envelope-open')
                    ->color(fn (ContactStatus $state): string => $state === ContactStatus::Unread ? 'primary' : 'gray'),
                TextColumn::make('name')
                    ->label('From')
                    ->weight(FontWeight::Bold)
                    ->limit(24)
                    ->tooltip(fn (ContactMessage $record): ?string => \Illuminate\Support\Str::limit((string) $record->message, 220))
                    ->description(fn (ContactMessage $record): string => \Illuminate\Support\Str::limit((string) $record->message, 50))
                    ->searchable(),
                TextColumn::make('subject_type')
                    ->label('Subject')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('created_at')
                    ->label('Received')
                    ->since()
                    ->color('gray')
                    ->alignEnd(),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->url(fn (ContactMessage $record): string => \App\Filament\Resources\ContactMessageResource::getUrl('view', ['record' => $record])),
            ])
            ->striped()
            ->paginated(false)
            ->searchable(false)
            ->emptyStateIcon('heroicon-o-envelope-open')
            ->emptyStateHeading('Inbox is empty')
            ->emptyStateDescription('No new messages from customers.');
    }
}
