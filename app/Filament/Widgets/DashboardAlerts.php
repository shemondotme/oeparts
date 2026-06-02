<?php

namespace App\Filament\Widgets;

use App\Enums\ContactStatus;
use App\Enums\OrderStatus;
use App\Enums\PartInquiryStatus;
use App\Enums\RefundStatus;
use App\Models\ContactMessage;
use App\Models\Order;
use App\Models\PartInquiry;
use App\Models\RefundRequest;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class DashboardAlerts extends BaseWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = -19;

    protected ?string $heading = 'Alerts';

    protected function getStats(): array
    {
        $awaitingConfirmation = Order::where('status', OrderStatus::Paid)->count();

        $pendingRefunds = RefundRequest::where('status', RefundStatus::Pending)->count();

        $newMessages = ContactMessage::where('status', ContactStatus::Unread)->count();

        $newInquiries = PartInquiry::where('status', PartInquiryStatus::New)->count();

        $failedJobs = DB::table('failed_jobs')->count();

        $lastBackup = DB::table('cron_logs')
            ->where('job_name', 'db:backup')
            ->where('status', 'success')
            ->orderByDesc('ran_at')
            ->value('ran_at');

        $backupStatus = $lastBackup
            ? now()->diffInHours($lastBackup) . 'h ago'
            : 'Never';

        $backupColor = $lastBackup && now()->diffInHours($lastBackup) > 26 ? 'danger' : 'success';

        return [
            Stat::make('Awaiting Confirmation', number_format($awaitingConfirmation))
                ->description($awaitingConfirmation > 0 ? 'Orders awaiting payment confirmation' : 'All clear')
                ->descriptionColor($awaitingConfirmation > 0 ? 'warning' : 'success')
                ->color($awaitingConfirmation > 0 ? 'warning' : 'success')
                ->icon('heroicon-o-bell-alert'),
            Stat::make('Refunds Pending', number_format($pendingRefunds))
                ->description($pendingRefunds > 0 ? 'Requires review' : 'No pending refunds')
                ->descriptionColor($pendingRefunds > 0 ? 'warning' : 'success')
                ->color($pendingRefunds > 0 ? 'warning' : 'success')
                ->icon('heroicon-o-arrow-uturn-left'),
            Stat::make('New Messages', number_format($newMessages))
                ->description($newMessages > 0 ? 'Contact form submissions' : 'No new messages')
                ->descriptionColor($newMessages > 0 ? 'info' : 'success')
                ->color($newMessages > 0 ? 'info' : 'success')
                ->icon('heroicon-o-inbox'),
            Stat::make('Part Inquiries', number_format($newInquiries))
                ->description($newInquiries > 0 ? 'Sourcing opportunities' : 'No new inquiries')
                ->descriptionColor($newInquiries > 0 ? 'info' : 'success')
                ->color($newInquiries > 0 ? 'info' : 'success')
                ->icon('heroicon-o-chat-bubble-left-right'),
            Stat::make('Failed Queue Jobs', number_format($failedJobs))
                ->description($failedJobs > 0 ? 'Requires investigation' : 'Queue healthy')
                ->descriptionColor($failedJobs > 0 ? 'danger' : 'success')
                ->color($failedJobs > 0 ? 'danger' : 'success')
                ->icon('heroicon-o-exclamation-circle'),
            Stat::make('Last Backup', $backupStatus)
                ->description($lastBackup ? 'Database backup' : 'No backup found')
                ->descriptionColor($backupColor)
                ->color($backupColor)
                ->icon('heroicon-o-circle-stack'),
        ];
    }
}
