<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Filament\Pages\Settings\SeoAudit;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "The SEO audit found something serious" — email + the admin bell. Sent by
 * SeoAuditMonitor only when a critical problem APPEARS (or, as a reminder,
 * has stayed for a week), never on every nightly run.
 */
class SeoAuditAlert extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<array{title: string, detail: string, still: bool}>  $issues
     */
    public function __construct(
        public readonly array $issues,
        public readonly string $siteName,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    private function headline(): string
    {
        $count = count($this->issues);
        $reminder = collect($this->issues)->every(fn (array $i) => $i['still']);

        return $reminder
            ? "SEO reminder: {$count} critical issue".($count === 1 ? '' : 's').' still not fixed'
            : "SEO alert: {$count} critical issue".($count === 1 ? '' : 's').' found';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $rows = [];
        foreach ($this->issues as $i => $issue) {
            $rows[($i + 1).'. '.$issue['title'].($issue['still'] ? ' (still open)' : '')] = $issue['detail'];
        }

        return (new MailMessage)
            ->subject($this->headline().' — '.$this->siteName)
            ->view(['emails.admin-notification', 'emails.admin-notification-text'], [
                'eyebrow' => 'SEO · AUDIT',
                'label' => 'SEO audit',
                'heading' => $this->headline(),
                'rows' => $rows,
                'bodyLabel' => 'What to do',
                'body' => 'Open the SEO Audit for the details and a link to fix each one. These problems can keep the site, or a whole section of it, out of search results.',
                'actionUrl' => SeoAudit::getUrl(panel: 'admin'),
                'actionLabel' => 'Open the SEO Audit',
            ]);
    }

    /** Bell-visible (data.format = 'filament') — see ContactMessageNotification::toDatabase(). */
    public function toDatabase(object $notifiable): array
    {
        return [
            ...FilamentNotification::make()
                ->title($this->headline())
                ->body(collect($this->issues)->pluck('title')->take(3)->implode(' · '))
                ->icon('heroicon-o-exclamation-triangle')
                ->iconColor('danger')
                ->viewData(['push_topic' => 'task_failed'])
                ->actions([
                    Action::make('open')
                        ->label('Open the SEO Audit')
                        ->url(SeoAudit::getUrl(panel: 'admin'))
                        ->markAsRead(),
                ])
                ->getDatabaseMessage(),
            ...$this->toArray($notifiable),
        ];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'seo_audit_alert',
            'issues' => array_column($this->issues, 'title'),
        ];
    }
}
