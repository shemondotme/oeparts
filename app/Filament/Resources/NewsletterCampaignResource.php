<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NewsletterCampaignResource\Pages;
use App\Filament\Support\AdminUi;
use App\Jobs\SendNewsletterCampaign;
use App\Models\NewsletterCampaign;
use App\Models\NewsletterSubscriber;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;

class NewsletterCampaignResource extends Resource
{
    protected static ?string $model = NewsletterCampaign::class;

    protected static ?int $navigationSort = 50;

    protected static ?string $modelLabel = 'Newsletter Campaign';

    protected static ?string $pluralModelLabel = 'Newsletter Campaigns';

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-megaphone';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Marketing';
    }

    public static function getNavigationLabel(): string
    {
        return 'Campaigns';
    }

    public static function form(Schema $schema): Schema
    {
        // Filament v5: Section lives in Schemas (Forms\Components\Section
        // doesn't exist — this fatal'd create/view/edit, killing the entire
        // campaign authoring flow). No statePath() here either: the pages
        // already set it, and a second level double-nests the form state.
        return $schema
            ->schema([
                \Filament\Schemas\Components\Section::make('Campaign Details')
                    ->description('Email content and subject line for this newsletter campaign.')
                    ->schema([
                        Forms\Components\TextInput::make('subject')
                            ->label('Subject Line')
                            ->required()
                            ->maxLength(200)
                            ->placeholder('e.g. Summer Parts Sale — Up to 30% Off')
                            ->helperText('The subject line subscribers will see in their inbox.'),
                        Forms\Components\Textarea::make('html_content')
                            ->label('Email Content (HTML)')
                            ->rows(15)
                            ->placeholder('<h1>Hello!</h1><p>We have great deals...</p>')
                            ->helperText('HTML email body. You can use inline styles for email compatibility.'),
                        Forms\Components\Textarea::make('plain_content')
                            ->label('Plain Text Version')
                            ->rows(8)
                            ->placeholder('Hello! We have great deals...')
                            ->helperText('Fallback plain text version for email clients that do not support HTML.'),
                    ])->columns(1),
                \Filament\Schemas\Components\Section::make('Scheduling')
                    ->description('Control when this campaign is sent to subscribers.')
                    ->schema([
                        Forms\Components\DateTimePicker::make('scheduled_at')
                            ->label('Scheduled Send Date')
                            ->nullable()
                            ->helperText('Leave empty to save as a draft. Set a future date/time and the campaign is sent automatically at that time.'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return AdminUi::configureTable($table)
            ->modifyQueryUsing(fn ($query) => $query->with('admin'))
            ->columns([
            Tables\Columns\TextColumn::make('subject')
                ->label('Subject')
                ->searchable()
                ->sortable()
                ->limit(50)
                ->weight(FontWeight::Bold),
            Tables\Columns\TextColumn::make('status')
                ->label('Status')
                ->badge()
                ->color(fn (string $state): string => match ($state) {
                    'draft'    => 'gray',
                    'scheduled'=> 'info',
                    'sending'  => 'warning',
                    'sent'     => 'success',
                    'failed'   => 'danger',
                    default    => 'gray',
                })
                ->icon(fn (string $state): string => match ($state) {
                    'draft'    => 'heroicon-o-clock',
                    'scheduled'=> 'heroicon-o-calendar',
                    'sending'  => 'heroicon-o-arrow-path',
                    'sent'     => 'heroicon-o-check-circle',
                    'failed'   => 'heroicon-o-x-circle',
                    default    => '',
                }),
            Tables\Columns\TextColumn::make('sent_count')
                ->label('Sent')
                ->numeric()
                ->fontMono()
                ->toggleable(isToggledHiddenByDefault: true),
            Tables\Columns\TextColumn::make('failed_count')
                ->label('Failed')
                ->numeric()
                ->fontMono()
                ->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray')
                ->toggleable(isToggledHiddenByDefault: true),
            Tables\Columns\TextColumn::make('admin.name')
                ->label('Created By')
                ->toggleable(isToggledHiddenByDefault: true),
            Tables\Columns\TextColumn::make('scheduled_at')
                ->label('Scheduled')
                ->dateTime('M j, Y H:i')
                ->sortable()
                ->placeholder('Draft')
                ->toggleable(isToggledHiddenByDefault: true),
            Tables\Columns\TextColumn::make('sent_at')
                ->label('Sent At')
                ->dateTime('M j, Y H:i')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
            Tables\Columns\TextColumn::make('created_at')
                ->label('Created')
                ->dateTime('M j, Y H:i')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Campaign Status')
                    ->options([
                        'draft'     => 'Draft',
                        'scheduled' => 'Scheduled',
                        'sending'   => 'Sending',
                        'sent'      => 'Sent',
                        'failed'    => 'Failed',
                    ])
                    ->helperText('Filter by the campaign\'s current status.'),
            ])
            ->actions([
                ...AdminUi::recordActions([
                    Tables\Actions\Action::make('send')
                        ->label('Send Now')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('success')
                        ->authorize('update')
                        ->requiresConfirmation()
                        ->modalHeading('Send Campaign')
                        ->modalDescription(fn (NewsletterCampaign $record): string =>
                            "This will send \"{$record->subject}\" to " . NewsletterSubscriber::where('is_active', true)->count() . " active subscribers."
                        )
                        ->action(function (NewsletterCampaign $record): void {
                            dispatch(new SendNewsletterCampaign($record));

                            Notification::make()
                                ->title('Campaign sending')
                                ->body("Sending \"{$record->subject}\" in the background.")
                                ->success()
                                ->send();
                        })
                        ->visible(fn (NewsletterCampaign $record): bool => $record->isDraft()),
                    Tables\Actions\Action::make('duplicateCampaign')
                        ->label('Duplicate')
                        ->icon('heroicon-o-document-duplicate')
                        ->color('gray')
                        ->authorize('update')
                        ->action(function (NewsletterCampaign $record) {
                            $duplicate = $record->replicate(['status', 'sent_count', 'failed_count', 'sent_at']);
                            $duplicate->created_by = auth('admin')->user()->id;
                            $duplicate->save();

                            Notification::make()
                                ->title('Campaign duplicated')
                                ->body("\"{$record->subject}\" duplicated as a new draft.")
                                ->success()
                                ->send();

                            return redirect()->to(route('filament.admin.resources.newsletter-campaigns.edit', $duplicate));
                        }),
                ]),
            ])
        ->bulkActions([
            Tables\Actions\BulkActionGroup::make([
                AdminUi::exportCsvBulkAction('Export Campaigns', [
                    'subject' => 'Subject',
                    'status' => 'Status',
                    'sent_count' => 'Sent',
                    'failed_count' => 'Failed',
                    'admin.name' => 'Created By',
                    'scheduled_at' => 'Scheduled',
                    'sent_at' => 'Sent At',
                    'created_at' => 'Created',
                ]),
                Tables\Actions\DeleteBulkAction::make(),
            ]),
        ])
            ->emptyStateIcon('heroicon-o-megaphone')
            ->emptyStateHeading('No campaigns created yet')
            ->emptyStateDescription('Create your first newsletter campaign to start reaching your subscribers with updates and promotions.')
            ->emptyStateActions([
                Tables\Actions\Action::make('create')
                    ->label('Create Campaign')
                    ->url(static::getUrl('create'))
                    ->icon('heroicon-o-plus')
                    ->button(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\NewsletterCampaignResource\RelationManagers\RecipientsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListNewsletterCampaigns::route('/'),
            'create' => Pages\CreateNewsletterCampaign::route('/create'),
            'view'   => Pages\ViewNewsletterCampaign::route('/{record}'),
            'edit'   => Pages\EditNewsletterCampaign::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return \App\Support\NavBadge::count('campaigns_draft', fn () => static::getModel()::where('status', 'draft')->count());
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return (int) \App\Support\NavBadge::count('campaigns_draft', fn () => static::getModel()::where('status', 'draft')->count()) > 5 ? 'danger' : 'warning';
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['subject', 'admin.name'];
    }
}
