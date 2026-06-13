<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\InvoiceService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class InvoiceController extends Controller
{
    public function download(Request $request, Order $order): Response
    {
        $admin = auth('admin')->user();

        if (!$admin || $admin->cannot('view orders')) {
            abort(403, 'Unauthorized.');
        }

        $invoiceService = app(InvoiceService::class);

        return $invoiceService->generate($order, true);
    }
}
