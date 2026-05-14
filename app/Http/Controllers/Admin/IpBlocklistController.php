<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\IpBlocklist as IpBlocklistModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class IpBlocklistController extends Controller
{
    /**
     * Display IP blocklist.
     */
    public function index(Request $request)
    {
        $query = IpBlocklistModel::with('blocker')->orderBy('created_at', 'desc');

        if ($request->filled('search')) {
            $query->where('ip_address', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        $blocklist = $query->paginate(20);

        return view('admin.settings.ip-blocklist', compact('blocklist'));
    }

    /**
     * Show form to create new IP block.
     */
    public function create()
    {
        return view('admin.settings.ip-blocklist-create');
    }

    /**
     * Store new IP block.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'ip_address' => 'required|ip|unique:ip_blocklists,ip_address',
            'reason' => 'required|string|max:255',
            'expires_at' => 'nullable|date|after:now',
        ]);

        IpBlocklistModel::create([
            'ip_address' => $validated['ip_address'],
            'reason' => $validated['reason'],
            'expires_at' => $validated['expires_at'] ?? null,
            'blocked_by' => Auth::guard('admin')->id(),
            'is_active' => true,
            'created_at' => now(),
        ]);

        return redirect()->route('admin.settings.ip-blocklist.index')
            ->with('success', 'IP address blocked successfully.');
    }

    /**
     * Toggle IP block status.
     */
    public function toggle(IpBlocklistModel $ipBlock)
    {
        $ipBlock->update(['is_active' => !$ipBlock->is_active]);

        return back()->with('success', 'IP block status updated.');
    }

    /**
     * Remove IP from blocklist.
     */
    public function destroy(IpBlocklistModel $ipBlock)
    {
        $ipBlock->delete();

        return back()->with('success', 'IP address removed from blocklist.');
    }
}
