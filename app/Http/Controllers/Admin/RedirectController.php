<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Redirect;
use Illuminate\Http\Request;

class RedirectController extends Controller
{
    /**
     * Display redirects.
     */
    public function index(Request $request)
    {
        $query = Redirect::orderBy('created_at', 'desc');

        if ($request->filled('search')) {
            $query->where(function($q) use ($request) {
                $q->where('from_url', 'like', '%' . $request->search . '%')
                  ->orWhere('to_url', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        $redirects = $query->paginate(20);

        return view('admin.settings.redirects', compact('redirects'));
    }

    /**
     * Show form to create new redirect.
     */
    public function create()
    {
        return view('admin.settings.redirects-create');
    }

    /**
     * Store new redirect.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'from_url' => 'required|string|max:500|unique:redirects,from_url',
            'to_url' => 'required|string|max:500',
            'type' => 'required|in:301,302',
            'is_active' => 'boolean',
        ]);

        Redirect::create([
            'from_url' => $this->normalizeUrl($validated['from_url']),
            'to_url' => $validated['to_url'],
            'type' => $validated['type'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return redirect()->route('admin.settings.redirects')
            ->with('success', 'Redirect created successfully.');
    }

    /**
     * Show form to edit redirect.
     */
    public function edit(Redirect $redirect)
    {
        return view('admin.settings.redirects-edit', compact('redirect'));
    }

    /**
     * Update redirect.
     */
    public function update(Request $request, Redirect $redirect)
    {
        $validated = $request->validate([
            'from_url' => ['required', 'string', 'max:500', \Illuminate\Validation\Rule::unique('redirects')->ignore($redirect->id)],
            'to_url' => 'required|string|max:500',
            'type' => 'required|in:301,302',
            'is_active' => 'boolean',
        ]);

        $redirect->update([
            'from_url' => $this->normalizeUrl($validated['from_url']),
            'to_url' => $validated['to_url'],
            'type' => $validated['type'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return redirect()->route('admin.settings.redirects')
            ->with('success', 'Redirect updated successfully.');
    }

    /**
     * Toggle redirect status.
     */
    public function toggle(Redirect $redirect)
    {
        $redirect->update(['is_active' => !$redirect->is_active]);
        return back()->with('success', 'Redirect status updated.');
    }

    /**
     * Delete redirect.
     */
    public function destroy(Redirect $redirect)
    {
        $redirect->delete();
        return back()->with('success', 'Redirect deleted successfully.');
    }

    /**
     * Normalize URL (remove leading slash if present).
     */
    private function normalizeUrl(string $url): string
    {
        return ltrim($url, '/');
    }
}
