<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RefundImageController extends Controller
{
    /**
     * Serve a customer-submitted refund image from the private disk.
     * Reachable only via a signed URL (issued by ImageEntry::temporaryUrl()
     * on the 'local' disk — see AppServiceProvider::boot()), scoped to the
     * refund-images/ directory only.
     */
    public function show(Request $request, string $path): StreamedResponse
    {
        $admin = auth('admin')->user();

        if (!$admin || (!$admin->hasRole('super_admin') && $admin->cannot('view refunds'))) {
            abort(403, 'Unauthorized.');
        }

        if (!str_starts_with($path, 'refund-images/') || !Storage::disk('local')->exists($path)) {
            abort(404);
        }

        return Storage::disk('local')->response($path);
    }
}
