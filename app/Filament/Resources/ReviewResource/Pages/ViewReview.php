<?php

namespace App\Filament\Resources\ReviewResource\Pages;

use App\Filament\Resources\ReviewResource;
use Filament\Actions;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewReview extends ViewRecord
{
    protected static string $resource = ReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(['default' => 1, 'xl' => 3])
                    ->columnSpanFull()
                    ->schema([
                        Group::make()
                            ->columnSpan(['default' => 1, 'xl' => 2])
                            ->schema([
                                Section::make('Review')
                                    ->icon('heroicon-o-star')
                                    ->schema([
                                        TextEntry::make('reviewer_name')
                                            ->label('Reviewer')
                                            ->weight(\Filament\Support\Enums\FontWeight::Medium),
                                        TextEntry::make('title')
                                            ->label('Title')
                                            ->placeholder('—'),
                                        TextEntry::make('comment')
                                            ->label('Comment')
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(2),
                            ]),

                        Group::make()
                            ->columnSpan(['default' => 1, 'xl' => 1])
                            ->schema([
                                Section::make('Moderation')
                                    ->icon('heroicon-o-adjustments-horizontal')
                                    ->schema([
                                        TextEntry::make('product.oem_number')
                                            ->label('Product')
                                            ->placeholder('—'),
                                        TextEntry::make('status')
                                            ->label('Status')
                                            ->badge()
                                            ->color(fn (string $state): string => match ($state) {
                                                'approved' => 'success',
                                                'rejected' => 'danger',
                                                default => 'warning',
                                            }),
                                        TextEntry::make('rating')
                                            ->label('Rating')
                                            ->formatStateUsing(fn (int $state): string => str_repeat('★', $state) . str_repeat('☆', 5 - $state))
                                            ->color('warning'),
                                        TextEntry::make('ip_address')
                                            ->label('Submitted From IP')
                                            ->placeholder('—'),
                                    ]),

                                Section::make('Record')
                                    ->icon('heroicon-o-clock')
                                    ->schema([
                                        TextEntry::make('created_at')
                                            ->label('Submitted')
                                            ->dateTime('M j, Y H:i'),
                                        TextEntry::make('updated_at')
                                            ->label('Last updated')
                                            ->since(),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}
