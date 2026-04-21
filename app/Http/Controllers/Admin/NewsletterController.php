<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NewsletterController extends Controller
{
    /**
     * Display a paginated list of newsletter subscribers with filters.
     */
    public function index(Request $request)
    {
        $query = NewsletterSubscriber::latest('subscribed_at');

        // Filter by active status
        if ($request->filled('is_active') && $request->is_active !== 'all') {
            $query->where('is_active', $request->is_active === 'active');
        }

        // Filter by language
        if ($request->filled('lang')) {
            $query->where('lang', $request->lang);
        }

        // Search by email
        if ($request->filled('search')) {
            $query->where('email', 'like', '%' . $request->search . '%');
        }

        $subscribers = $query->paginate(30)->withQueryString();

        return view('admin.cms.newsletter.index', [
            'subscribers' => $subscribers,
        ]);
    }

    /**
     * Show the form for creating a new subscriber (manual add).
     */
    public function create()
    {
        return view('admin.cms.newsletter.create');
    }

    /**
     * Store a newly created subscriber in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'email'     => ['required', 'email', 'max:255', 'unique:newsletter_subscribers,email'],
            'lang'      => ['required', Rule::in(['en', 'de', 'lt', 'fr', 'es'])],
            'is_active' => ['boolean'],
        ]);

        $validated['subscribed_at'] = now();
        $validated['ip_address']    = $request->ip();

        NewsletterSubscriber::create($validated);

        return redirect()->route('admin.cms.newsletter.index')
            ->with('success', __('Subscriber added successfully.'));
    }

    /**
     * Display the specified subscriber.
     */
    public function show(NewsletterSubscriber $newsletter)
    {
        return view('admin.cms.newsletter.show', [
            'subscriber' => $newsletter,
        ]);
    }

    /**
     * Show the form for editing the specified subscriber.
     */
    public function edit(NewsletterSubscriber $newsletter)
    {
        return view('admin.cms.newsletter.edit', [
            'subscriber' => $newsletter,
        ]);
    }

    /**
     * Update the specified subscriber in storage.
     */
    public function update(Request $request, NewsletterSubscriber $newsletter)
    {
        $validated = $request->validate([
            'email'     => ['required', 'email', 'max:255', Rule::unique('newsletter_subscribers')->ignore($newsletter->id)],
            'lang'      => ['required', Rule::in(['en', 'de', 'lt', 'fr', 'es'])],
            'is_active' => ['boolean'],
        ]);

        $newsletter->update($validated);

        return redirect()->route('admin.cms.newsletter.index')
            ->with('success', __('Subscriber updated successfully.'));
    }

    /**
     * Remove the specified subscriber from storage.
     */
    public function destroy(NewsletterSubscriber $newsletter)
    {
        $newsletter->delete();

        return redirect()->route('admin.cms.newsletter.index')
            ->with('success', __('Subscriber deleted successfully.'));
    }

    /**
     * Export active subscribers to CSV.
     */
    public function export(Request $request)
    {
        $subscribers = NewsletterSubscriber::where('is_active', true)->get();

        $csv = fopen('php://temp', 'w');
        fputcsv($csv, ['Email', 'Language', 'Subscribed At', 'Active']);

        foreach ($subscribers as $sub) {
            fputcsv($csv, [
                $sub->email,
                $sub->lang,
                $sub->subscribed_at->format('Y-m-d H:i:s'),
                $sub->is_active ? 'yes' : 'no',
            ]);
        }

        rewind($csv);
        $csvContent = stream_get_contents($csv);
        fclose($csv);

        return response($csvContent, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="newsletter_subscribers_' . date('Y-m-d') . '.csv"',
        ]);
    }

    /**
     * Toggle subscription (is_active) status.
     */
    public function toggleStatus(NewsletterSubscriber $newsletter)
    {
        $newsletter->update([
            'is_active'        => ! $newsletter->is_active,
            'unsubscribed_at'  => $newsletter->is_active ? now() : null,
        ]);

        return redirect()->back()
            ->with('success', __('Subscription status updated.'));
    }
}
