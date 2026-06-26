<?php

namespace App\Filament\Pages\System;

use App\Filament\Clusters\System;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\File;

class LogViewerPage extends Page
{
    protected static ?string $cluster = System::class;

    protected static ?string $title = 'Log Viewer';

    protected string $view = 'filament.pages.system.log-viewer';

    public static function getNavigationGroup(): ?string
    {
        return System::getNavigationGroup();
    }

    public string $selectedFile = '';

    public string $levelFilter = '';

    public string $searchQuery = '';

    public static function getNavigationSort(): ?int
    {
        return 45;
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-document-text';
    }

    public static function canAccess(): bool
    {
        return auth('admin')->user()->hasRole('super_admin');
    }

    public function getLogFiles(): array
    {
        $logPath = storage_path('logs');

        if (! File::isDirectory($logPath)) {
            return [];
        }

        $files = collect(File::files($logPath))
            ->filter(fn ($file) => $file->getExtension() === 'log')
            ->sortByDesc(fn ($file) => $file->getMTime())
            ->values();

        return $files->map(fn ($file) => [
            'name' => $file->getFilename(),
            'size' => $this->formatBytes($file->getSize()),
            'last_modified' => $file->getMTime(),
        ])->toArray();
    }

    public function getLogContent(): array
    {
        if (empty($this->selectedFile)) {
            $files = $this->getLogFiles();
            if (! empty($files)) {
                $this->selectedFile = $files[0]['name'];
            }
        }

        if (empty($this->selectedFile)) {
            return [];
        }

        $filepath = storage_path('logs/' . basename($this->selectedFile));

        if (! file_exists($filepath)) {
            return [];
        }

        $lines = file($filepath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $lines = array_reverse($lines);

        $filtered = [];
        foreach ($lines as $line) {
            if (! empty($this->levelFilter)) {
                $level = $this->getLogLevel($line);
                if ($level !== $this->levelFilter) {
                    continue;
                }
            }

            if (! empty($this->searchQuery)) {
                if (stripos($line, $this->searchQuery) === false) {
                    continue;
                }
            }

            $filtered[] = [
                'level' => $this->getLogLevel($line),
                'content' => $line,
                'color' => $this->getLevelColor($this->getLogLevel($line)),
            ];

            if (count($filtered) >= 200) {
                break;
            }
        }

        return $filtered;
    }

    private function getLogLevel(string $line): string
    {
        if (stripos($line, '[error]') !== false) {
            return 'error';
        }

        if (stripos($line, '[warning]') !== false || stripos($line, '[notice]') !== false) {
            return 'warning';
        }

        if (stripos($line, '[info]') !== false) {
            return 'info';
        }

        if (stripos($line, '[debug]') !== false) {
            return 'debug';
        }

        if (stripos($line, '[critical]') !== false || stripos($line, '[alert]') !== false || stripos($line, '[emergency]') !== false) {
            return 'critical';
        }

        return 'default';
    }

    private function getLevelColor(string $level): string
    {
        return match ($level) {
            'error' => 'var(--color-danger-500)',
            'warning' => 'var(--color-warning-500)',
            'info' => 'var(--color-info-500)',
            'debug' => 'var(--color-text-muted)',
            'critical' => 'var(--color-danger-600)',
            default => 'var(--color-text-secondary)',
        };
    }

    public function clearLog(): void
    {
        if (empty($this->selectedFile)) {
            return;
        }

        $filepath = storage_path('logs/' . basename($this->selectedFile));

        if (file_exists($filepath)) {
            file_put_contents($filepath, '');
        }

        Notification::make()
            ->title('Log file cleared')
            ->success()
            ->send();
    }

    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
