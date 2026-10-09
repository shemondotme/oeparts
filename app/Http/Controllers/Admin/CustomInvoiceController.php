<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomInvoice;
use App\Services\CustomInvoiceService;
use Illuminate\Http\Response;

class CustomInvoiceController extends Controller
{
    public function pdf(CustomInvoice $customInvoice, CustomInvoiceService $service): Response
    {
        $admin = auth('admin')->user();

        if (! $admin || ! ($admin->hasRole('super_admin') || $admin->can('view custom invoices'))) {
            abort(403, 'Unauthorized.');
        }

        $pdf = $service->pdf($customInvoice);

        // ?inline=1 opens it in the browser tab (a preview); otherwise it is downloaded.
        return request()->boolean('inline')
            ? $pdf->stream($service->filename($customInvoice))
            : $pdf->download($service->filename($customInvoice));
    }
}
