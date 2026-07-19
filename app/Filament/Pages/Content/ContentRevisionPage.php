<?php

namespace App\Filament\Pages\Content;

use App\Filament\Clusters\Content as ContentCluster;
use App\Models\ContentRevision;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;

class ContentRevisionPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $cluster = ContentCluster::class;

    protected static ?string $title = 'Content Revision History';

    protected string $view = 'filament.pages.content.content-revision';

    public static function getNavigationSort(): ?int
    {
        // Last in the Content cluster — it's a read-only audit trail, not a
        // content type, so it sits after all the editable items.
        return 100;
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-clock';
    }

    public static function getNavigationLabel(): string
    {
        return 'Revision History';
    }

    public function getSubheading(): ?string
    {
        return 'Audit trail of all content changes across sections, pages, blog posts, and FAQs.';
    }

    public static function canAccess(): bool
    {
        return auth('admin')->user()?->hasAnyRole(['super_admin', 'admin']) ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ContentRevision::query()
                    ->with(['revisionable', 'admin'])
                    ->orderByDesc('created_at')
            )
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('When')
                    ->since()
                    ->sortable()
                    ->fontMono()
                    ->size('sm'),

                Tables\Columns\TextColumn::make('admin.name')
                    ->label('By')
                    ->searchable()
                    ->sortable()
                    ->size('sm'),

                Tables\Columns\TextColumn::make('revisionable_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => class_basename($state))
                    ->color(fn ($state): string => match (class_basename($state)) {
                        'Section' => 'info',
                        'BlogPost' => 'success',
                        'Page' => 'primary',
                        'Faq' => 'warning',
                        default => 'gray',
                    })
                    ->searchable()
                    ->size('sm'),

                Tables\Columns\TextColumn::make('revisionable_id')
                    ->label('Record ID')
                    ->fontMono()
                    ->size('sm'),

                Tables\Columns\TextColumn::make('content_snapshot')
                    ->label('Snapshot Keys')
                    ->formatStateUsing(fn ($state): string => is_array($state) ? implode(', ', array_keys($state)) : '—')
                    ->limit(50)
                    ->wrap()
                    ->size('sm'),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50, 100])
            ->poll('60s')
            ->filters([
                Tables\Filters\SelectFilter::make('revisionable_type')
                    ->label('Content Type')
                    ->options([
                        'App\\Models\\Section' => 'Section',
                        'App\\Models\\BlogPost' => 'Blog Post',
                        'App\\Models\\Page' => 'Page',
                        'App\\Models\\Faq' => 'FAQ',
                    ])
                    ->multiple(),
                Tables\Filters\SelectFilter::make('admin_id')
                    ->label('Admin')
                    ->options(fn () => \App\Models\Admin::pluck('name', 'id'))
                    ->searchable(),
                Tables\Filters\Filter::make('created_at')
                    ->label('Date Range')
                    ->form([
                        Select::make('created_at')
                            ->options([
                                'today' => 'Today',
                                'yesterday' => 'Yesterday',
                                'week' => 'This Week',
                                'month' => 'This Month',
                                'quarter' => 'This Quarter',
                            ])
                            ->placeholder('All Time'),
                    ])
                    ->query(function ($query, array $data): void {
                        if (empty($data['created_at'])) {
                            return;
                        }

                        $query->whereDate('created_at', match ($data['created_at']) {
                            'today' => now()->toDateString(),
                            'yesterday' => now()->subDay()->toDateString(),
                            'week' => now()->startOfWeek()->toDateString(),
                            'month' => now()->startOfMonth()->toDateString(),
                            'quarter' => now()->startOfQuarter()->toDateString(),
                            default => now()->toDateString(),
                        });
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('viewSnapshot')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading('Content Snapshot')
                    ->modalContent(function ($record) {
                        return view('filament.pages.content.revision-detail', ['record' => $record]);
                    })
                    ->modalSubmitAction(false),
            ]);
    }
}
