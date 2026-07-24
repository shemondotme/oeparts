<?php

namespace App\Services;

use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class InvoiceService
{
    /**
     * Generate PDF invoice for an order.
     *
     * @param  bool  $download  Whether to return download response or PDF content
     * @param  bool  $skipAuthorization Whether to bypass user authorization check (useful for system jobs)
     * @return \Barryvdh\DomPDF\PDF|Response
     */
    public function generate(Order $order, bool $download = false, bool $skipAuthorization = false): \Barryvdh\DomPDF\PDF|\Illuminate\Http\Response
    {
        if (! $skipAuthorization) {
            $this->authorize($order);
        }

        try {
            $order->loadMissing(['items.product']);

            $snapshotAddress = $this->addressFromOrderSnapshot($order);

            $data = [
                'order' => $order,
                'user' => $order->user,
                'items' => $order->items,
                'billingAddress' => $snapshotAddress,
                'shippingAddress' => $snapshotAddress,
                'settings' => [
                    'company_name' => settings('company.name', 'OeParts'),
                    'company_address' => settings('company.address', ''),
                    'company_vat' => settings('company.vat_number', ''),
                    'company_registration' => settings('company.registration_number', ''),
                    'company_email' => settings('company.email', 'info@oeparts.lt'),
                    'company_phone' => settings('company.phone', ''),
                ],
            ];

            $pdf = Pdf::loadView('pdf.invoice', $data);

            if ($download) {
                return $pdf->download("invoice-{$order->order_number}.pdf");
            }

            return $pdf;
        } catch (\Exception $e) {
            Log::error('Invoice generation failed', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Build a bill/ship address object from order shipping snapshot (no saved Address rows).
     */
    private function addressFromOrderSnapshot(Order $order): object
    {
        $name = trim(mb_strtolower((string) ($order->shipping_name ?? '')));
        $parts = $name === '' ? ['', ''] : preg_split('/\s+/u', $name, 2);

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
        $pdf = $this->generate($order, false, true);
        $filename = "invoices/{$order->order_number}.pdf";

        $content = $pdf->output();
        if (strlen($content) > 10 * 1024 * 1024) {
            throw new \RuntimeException('PDF too large');
        }

        try {
            Storage::disk('local')->put($filename, $content);
        } catch (\Exception $e) {
            Log::error('Failed to save invoice to storage', [
                'order_id' => $order->id,
                'filename' => $filename,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        return $filename;
    }

    /**
     * Check if invoice exists in storage.
     */
    public function exists(Order $order): bool
    {
        $filename = "invoices/{$order->order_number}.pdf";

        return Storage::disk('local')->exists($filename);
    }

    /**
     * Get invoice from storage.
     */
    public function getFromStorage(Order $order): ?string
    {
        $this->authorize($order);

        $filename = "invoices/{$order->order_number}.pdf";

        if (!Storage::disk('local')->exists($filename)) {
            Log::error('Invoice file not found in storage', [
                'order_id' => $order->id,
                'filename' => $filename,
            ]);

            return null;
        }

        return Storage::disk('local')->get($filename);
    }

    /**
     * Verify the order belongs to the requesting user or user is admin.
     */
    private function authorize(Order $order): void
    {
        $admin = Auth::guard('admin')->user();
        if ($admin) {
            return;
        }

        $user = Auth::guard('web')->user();
        if ($user && $order->user_id === $user->id) {
            return;
        }

        abort(403, 'Unauthorized access to invoice.');
    }
}
