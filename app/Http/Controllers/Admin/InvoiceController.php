<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\InvoiceService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class InvoiceController extends Controller
{
    public function download(Request $request, Order $order): Response
    {
        $admin = auth('admin')->user();

        if (! $admin || $admin->cannot('view orders')) {
            abort(403, 'Unauthorized.');
        }

        $invoiceService = app(InvoiceService::class);

        return $invoiceService->download($order);
    }

    /**
     * Warehouse packing slip: the items and the delivery address, no prices.
     */
    public function packingSlip(Request $request, Order $order): Response
    {
        $admin = auth('admin')->user();

        if (! $admin || $admin->cannot('view orders')) {
            abort(403, 'Unauthorized.');
        }

        $order->loadMissing('items.product');

        return Pdf::loadView('pdf.packing-slip', [
            'order' => $order,
            'settings' => [
                'company_name' => settings('company.name', 'OeParts'),
                'company_email' => settings('company.email', ''),
                'company_phone' => settings('company.phone', ''),
            ],
        ])->download("packing-slip-{$order->order_number}.pdf");
    }
}
