<?php

namespace App\Filament\Pages\System;

use App\Filament\Clusters\System;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class BackupDashboard extends Page
{
    protected static ?string $cluster = System::class;

    protected static ?string $title = 'Backup Management';

    protected string $view = 'filament.pages.system.backup-dashboard';

    public static function getNavigationGroup(): ?string
    {
        return System::getNavigationGroup();
    }

    public static function getNavigationSort(): ?int
    {
        return 25;
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-archive-box';
    }

    public static function canAccess(): bool
    {
        return auth('admin')->user()->hasRole('super_admin');
    }

    public function getBackups(): array
    {
        $backupPath = storage_path('app/backups');

        if (! File::isDirectory($backupPath)) {
            File::makeDirectory($backupPath, 0755, true);
            return [];
        }

        $files = collect(File::files($backupPath))
            ->filter(fn ($file) => $file->getExtension() === 'zip')
            ->sortByDesc('lastModified')
            ->values();

        return $files->map(fn ($file) => [
            'name' => $file->getFilename(),
            'size' => $this->formatBytes($file->getSize()),
            'size_bytes' => $file->getSize(),
            'created_at' => $file->lastModified(),
            'created_diff' => now()->diffForHumans(now()->subSeconds(time() - $file->lastModified())),
        ])->toArray();
    }

    public function getBackupStats(): array
    {
        $backups = $this->getBackups();
        $totalSize = collect($backups)->sum('size_bytes');
        $lastBackup = $backups[0] ?? null;

        return [
            'total_backups' => count($backups),
            'total_size' => $this->formatBytes($totalSize),
            'last_backup' => $lastBackup ? $lastBackup['created_diff'] : 'Never',
            'last_backup_name' => $lastBackup ? $lastBackup['name'] : '—',
        ];
    }

    public function createBackup(): void
    {
        $backupPath = storage_path('app/backups');

        if (! File::isDirectory($backupPath)) {
            File::makeDirectory($backupPath, 0755, true);
        }

        $filename = 'backup_' . now()->format('Y-m-d_His') . '.zip';
        $filepath = $backupPath . '/' . $filename;

        $zip = new ZipArchive();

        if ($zip->open($filepath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            Notification::make()
                ->title('Backup failed')
                ->body('Could not create zip archive.')
                ->danger()
                ->send();

            return;
        }

        $databasePath = database_path('database.sqlite');
        if (file_exists($databasePath)) {
            $zip->addFile($databasePath, 'database.sqlite');
        }

        $configFiles = File::allFiles(config_path());
        foreach ($configFiles as $file) {
            $relativePath = 'config/' . ltrim(str_replace(config_path(), '', $file->getPathname()), DIRECTORY_SEPARATOR);
            $zip->addFile($file->getPathname(), $relativePath);
        }

        $migrationFiles = File::allFiles(database_path('migrations'));
        foreach ($migrationFiles as $file) {
            $relativePath = 'migrations/' . ltrim(str_replace(database_path('migrations'), '', $file->getPathname()), DIRECTORY_SEPARATOR);
            $zip->addFile($file->getPathname(), $relativePath);
        }

        $zip->close();

        Notification::make()
            ->title('Backup created')
            ->body("File: {$filename} (" . $this->formatBytes(filesize($filepath)) . ")")
            ->success()
            ->send();
    }

    public function deleteBackup(string $filename): void
    {
        $filepath = storage_path('app/backups/' . basename($filename));

        if (file_exists($filepath)) {
            unlink($filepath);
        }

        Notification::make()
            ->title('Backup deleted')
            ->success()
            ->send();
    }

    public function downloadBackup(string $filename): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $filepath = storage_path('app/backups/' . basename($filename));

        if (! file_exists($filepath)) {
            abort(404, 'Backup file not found.');
        }

        return response()->streamDownload(function () use ($filepath) {
            echo file_get_contents($filepath);
        }, basename($filename));
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
