<?php

namespace App\Services;

use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class InvoiceService
{
    /**
     * Generate PDF invoice for an order.
     *
     * @param  bool  $download  Whether to return download response or PDF content
     * @return \Barryvdh\DomPDF\PDF|Response
     */
    public function generate(Order $order, bool $download = false)
    {
        $order->loadMissing(['items.product']);

        $snapshotAddress = $this->addressFromOrderSnapshot($order);

        $data = [
            'order' => $order,
            'user' => $order->user,
            'items' => $order->items,
            'billingAddress' => $snapshotAddress,
            'shippingAddress' => $snapshotAddress,
            'settings' => [
                'company_name' => settings('company.name', 'OEMHub'),
                'company_address' => settings('company.address', ''),
                'company_vat' => settings('company.vat_number', ''),
                'company_email' => settings('company.email', 'support@oemhub.com'),
                'company_phone' => settings('company.phone', ''),
            ],
        ];

        $pdf = Pdf::loadView('pdf.invoice', $data);

        if ($download) {
            return $pdf->download("invoice-{$order->order_number}.pdf");
        }

        return $pdf;
    }

    /**
     * Build a bill/ship address object from order shipping snapshot (no saved Address rows).
     */
    private function addressFromOrderSnapshot(Order $order): object
    {
        $name = trim((string) ($order->shipping_name ?? ''));
        $parts = $name === '' ? ['', ''] : preg_split('/\s+/', $name, 2);

        return (object) [
            'first_name' => $parts[0] ?? '',
            'last_name' => $parts[1] ?? '',
            'company' => $order->company_name,
            'address_line_1' => (string) ($order->shipping_address_line1 ?? ''),
            'address_line_2' => null,
            'city' => (string) ($order->shipping_city ?? ''),
            'state' => '',
            'postal_code' => (string) ($order->shipping_postal_code ?? ''),
            'country_code' => (string) ($order->shipping_country_code ?? ''),
            'phone' => null,
        ];
    }

    /**
     * Save invoice to storage and return path.
     */
    public function saveToStorage(Order $order): string
    {
        $pdf = $this->generate($order);
        $filename = "invoices/{$order->order_number}.pdf";
        Storage::disk('private')->put($filename, $pdf->output());

        return $filename;
    }

    /**
     * Check if invoice exists in storage.
     */
    public function exists(Order $order): bool
    {
        $filename = "invoices/{$order->order_number}.pdf";

        return Storage::disk('private')->exists($filename);
    }

    /**
     * Get invoice from storage.
     */
    public function getFromStorage(Order $order)
    {
        $filename = "invoices/{$order->order_number}.pdf";

        return Storage::disk('private')->get($filename);
    }
}
