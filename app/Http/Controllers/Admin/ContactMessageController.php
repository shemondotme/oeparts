<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ContactStatus;
use App\Enums\ContactSubjectType;
use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContactMessageController extends Controller
{
    /**
     * Display a paginated list of contact messages with filters.
     */
    public function index(Request $request)
    {
        $query = ContactMessage::latest();

        // Filter by status
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Filter by subject type
        if ($request->filled('subject_type')) {
            $query->where('subject_type', $request->subject_type);
        }

        // Search by email or name
        if ($request->filled('search')) {
            $query->where('email', 'like', '%' . $request->search . '%')
                ->orWhere('name', 'like', '%' . $request->search . '%')
                ->orWhere('subject', 'like', '%' . $request->search . '%');
        }

        $messages = $query->paginate(30)->withQueryString();

        return view('admin.cms.contact.index', [
            'messages' => $messages,
            'statuses' => ContactStatus::cases(),
            'subjectTypes' => ContactSubjectType::cases(),
        ]);
    }

    /**
     * Display the specified contact message.
     */
    public function show(ContactMessage $contact)
    {
        return view('admin.cms.contact.show', [
            'message' => $contact,
        ]);
    }

    /**
     * Update the status of a contact message.
     */
    public function updateStatus(Request $request, ContactMessage $contact)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(ContactStatus::class)],
        ]);

        $contact->update([
            'status' => $validated['status'],
        ]);

        return redirect()->route('admin.cms.contact.show', $contact)
            ->with('success', __('Status updated successfully.'));
    }

    /**
     * Add a reply to a contact message.
     */
    public function addReply(Request $request, ContactMessage $contact)
    {
        $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'send_email' => ['boolean'],
        ]);

        // Mark as read when an admin replies
        if ($contact->status === ContactStatus::Unread) {
            $contact->update(['status' => ContactStatus::Read]);
        }

        // TODO: Send email if requested (Sprint 17)

        return redirect()->route('admin.cms.contact.show', $contact)
            ->with('success', __('Reply added successfully.'));
    }

    /**
     * Remove the specified contact message from storage.
     */
    public function destroy(ContactMessage $contact)
    {
        $contact->replies()->delete();
        $contact->delete();

        return redirect()->route('admin.cms.contact.index')
            ->with('success', __('Contact message deleted successfully.'));
    }

    /**
     * Export contact messages to CSV.
     */
    public function export(Request $request)
    {
        $messages = ContactMessage::all();

        $csv = fopen('php://temp', 'w');
        fputcsv($csv, ['ID', 'Name', 'Email', 'Subject Type', 'Status', 'Created At']);

        foreach ($messages as $msg) {
            fputcsv($csv, [
                $msg->id,
                $msg->name,
                $msg->email,
                $msg->subject_type->value,
                $msg->status->value,
                $msg->created_at->format('Y-m-d H:i:s'),
            ]);
        }

        rewind($csv);
        $csvContent = stream_get_contents($csv);
        fclose($csv);

        return response($csvContent, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="contact_messages_' . date('Y-m-d') . '.csv"',
        ]);
    }
}