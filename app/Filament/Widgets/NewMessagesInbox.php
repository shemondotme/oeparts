<?php

namespace App\Filament\Widgets;

use App\Enums\ContactStatus;
use App\Filament\Concerns\HasWidgetExport;
use App\Models\ContactMessage;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class NewMessagesInbox extends TableWidget
{
    use Concerns\HasWidgetRoles;
    use HasWidgetExport;

    protected static bool $isLazy = true;

    protected static ?string $heading = 'New Messages';

    protected ?string $pollingInterval = '60s';

    protected int | string | array $columnSpan = ['md' => 1, 'xl' => 1];

    protected static ?int $sort = -31;

    public function getDescription(): ?string
    {
        return 'Recent unread contact messages';
    }

    protected function getExportHeaders(): array
    {
        return ['From', 'Subject', 'Message', 'Status', 'Received'];
    }

    protected function getExportRows(): iterable
    {
        return ContactMessage::query()
            ->latest()
            ->get()
            ->map(fn (ContactMessage $msg): array => [
                $msg->name,
                $msg->subject_type ?? '—',
                mb_substr(strip_tags($msg->message ?? ''), 0, 120),
                $msg->status instanceof ContactStatus ? $msg->status->name : (string) $msg->status,
                $msg->created_at?->format('d M Y H:i') ?? '—',
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [$this->getExportActions()];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ContactMessage::query()
                    ->latest()
                    ->limit(8)
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('From')
                    ->limit(20)
                    ->searchable(),
                Tables\Columns\TextColumn::make('subject_type')
                    ->label('Subject')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('message')
                    ->label('Message')
                    ->limit(40)
                    ->html(false),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Time')
                    ->since(),
                Tables\Columns\IconColumn::make('status')
                    ->label('')
                    ->icon(fn (ContactStatus $state): string => $state === ContactStatus::Unread ? 'heroicon-o-envelope' : 'heroicon-o-envelope-open')
                    ->color(fn (ContactStatus $state): string => $state === ContactStatus::Unread ? 'primary' : 'gray'),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->url(fn (ContactMessage $record): string => \App\Filament\Resources\ContactMessageResource::getUrl('view', ['record' => $record])),
            ])
            ->paginated(false)
            ->searchable(false)
            ->emptyStateIcon('heroicon-o-envelope-open')
            ->emptyStateHeading('Inbox is empty')
            ->emptyStateDescription('No new messages from customers.');
    }
}
