<?php

namespace App\Filament\Concerns;

use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;

/**
 * Adds CSV, PDF, and PNG export to any dashboard widget.
 *
 * Widgets must implement:
 *   protected function getExportHeaders(): array
 *   protected function getExportRows(): iterable<array>
 *
 * Downloads are triggered via dispatched browser events handled by
 * dashboard-canvas.js — avoids Livewire/StreamedResponse pipeline conflicts.
 */
trait HasWidgetExport
{
    protected function getExportFilename(): string
    {
        return str_replace(' ', '-', strtolower(static::$heading ?? 'export')) . '-' . date('Y-m-d');
    }

    protected function getExportHeaders(): array
    {
        return [];
    }

    protected function getExportRows(): iterable
    {
        return [];
    }

    public function exportToCsv(): void
    {
        $headers  = $this->getExportHeaders();
        $rows     = collect($this->getExportRows())->map(fn ($r) => array_values((array) $r));
        $filename = $this->getExportFilename() . '.csv';

        $buf = '';
        $stream = fopen('php://temp', 'r+');
        if ($headers) {
            fputcsv($stream, $headers);
        }
        foreach ($rows as $row) {
            fputcsv($stream, $row);
        }
        rewind($stream);
        $buf = stream_get_contents($stream);
        fclose($stream);

        $this->dispatch('op:download-blob',
            content: base64_encode($buf),
            mime: 'text/csv;charset=UTF-8',
            filename: $filename,
        );
    }

    public function exportToPdf(): void
    {
        $pdf = Pdf::loadView('filament.exports.widget-table', [
            'title'   => static::$heading ?? 'Export',
            'headers' => $this->getExportHeaders(),
            'rows'    => collect($this->getExportRows())->map(fn ($r) => array_values((array) $r)),
        ]);

        $this->dispatch('op:download-blob',
            content: base64_encode($pdf->output()),
            mime: 'application/pdf',
            filename: $this->getExportFilename() . '.pdf',
        );
    }

    public function exportPng(): void
    {
        $this->dispatch('op:chart-export-png',
            widgetId: $this->getId(),
            filename: $this->getExportFilename() . '.png',
        );
    }

    /**
     * Auto-wires export actions into the Filament table header.
     * Called by InteractsWithTable::makeTable() — only runs for TableWidget subclasses.
     */
    protected function getTableHeaderActions(): array
    {
        return [$this->getExportActions()];
    }

    /**
     * Returns the export ActionGroup for use in widget header or table header actions.
     * Pass $chartOnly = true for chart widgets that only support PNG.
     */
    protected function getExportActions(bool $chartOnly = false): ActionGroup
    {
        $actions = [];

        if ($chartOnly) {
            $actions[] = Action::make('export_png')
                ->label('PNG Image')
                ->icon('heroicon-o-photo')
                ->action(fn () => $this->exportPng());
        } else {
            $actions[] = Action::make('export_csv')
                ->label('CSV')
                ->icon('heroicon-o-table-cells')
                ->action(fn () => $this->exportToCsv());

            $actions[] = Action::make('export_pdf')
                ->label('PDF')
                ->icon('heroicon-o-document')
                ->action(fn () => $this->exportToPdf());
        }

        return ActionGroup::make($actions)
            ->label('Export')
            ->icon('heroicon-o-arrow-down-tray')
            ->size(\Filament\Support\Enums\Size::Small)
            ->color('gray')
            ->button();
    }
}
