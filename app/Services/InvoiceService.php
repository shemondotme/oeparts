<?php

namespace App\Services;

use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class InvoiceService
{
    /**
     * Generate PDF invoice for an order.
     *
     * @param Order $order
     * @param bool $download Whether to return download response or PDF content
     * @return \Barryvdh\DomPDF\PDF|\Illuminate\Http\Response
     */
    public function generate(Order $order, bool $download = false)
    {
        $data = [
            'order' => $order,
            'user' => $order->user,
            'items' => $order->items,
            'billingAddress' => $order->billingAddress,
            'shippingAddress' => $order->shippingAddress,
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