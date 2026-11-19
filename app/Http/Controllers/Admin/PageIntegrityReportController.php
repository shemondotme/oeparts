<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Receives the page guard's report that an admin page reached the browser with fewer bytes than the server sent (see
 * App\Http\Middleware\AnnouncePageSize). One log line per report in storage/logs/slow-admin-*.log — numbers only, no
 * page content, no IP address.
 */
class PageIntegrityReportController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'path' => ['required', 'string', 'max:200'],
            'expected' => ['required', 'integer', 'min:0', 'max:50000000'],
            'received' => ['required', 'integer', 'min:0', 'max:50000000'],
            'transfer' => ['nullable', 'integer', 'min:0', 'max:50000000'],
            'protocol' => ['nullable', 'string', 'max:20'],
            'via' => ['nullable', 'string', 'max:20'],
            'millis' => ['nullable', 'integer', 'min:0', 'max:3600000'],
        ]);

        Log::channel('slow_admin')->warning('Admin page reached the browser incomplete', [
            'path' => $data['path'],
            'server_sent_bytes' => $data['expected'],
            'browser_received_bytes' => $data['received'],
            'missing_bytes' => $data['expected'] - $data['received'],
            'transfer_bytes' => $data['transfer'] ?? null,
            'protocol' => $data['protocol'] ?? null,
            'via' => $data['via'] ?? null,
            'milliseconds' => $data['millis'] ?? null,
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 120),
        ]);

        return response()->json(['ok' => true]);
    }
}
