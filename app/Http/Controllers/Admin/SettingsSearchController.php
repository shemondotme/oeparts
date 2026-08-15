<?php

namespace App\Http\Controllers\Admin;

use App\Filament\Support\SettingsSearchIndex;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Backs the Ctrl+K settings command palette (resources/views/filament/
 * topbar/settings-search.blade.php). Matches against the hand-maintained
 * SettingsSearchIndex — Filament's own global search can't cover
 * SettingsPage fields (see SettingsSearchIndex's class docblock for why).
 *
 * Same access gate as SettingsPage::canAccess() — an admin who can't open
 * ANY settings page shouldn't be able to discover what fields exist on
 * one via this endpoint either.
 */
class SettingsSearchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless(
            auth('admin')->user()?->hasAnyRole(['super_admin', 'admin']) ?? false,
            403
        );

        $query = (string) $request->query('q', '');

        if (mb_strlen(trim($query)) < 2) {
            return response()->json(['results' => []]);
        }

        return response()->json([
            'results' => SettingsSearchIndex::search($query),
        ]);
    }
}
