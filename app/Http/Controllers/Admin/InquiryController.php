<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PartInquiryStatus;
use App\Http\Controllers\Controller;
use App\Models\PartInquiry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InquiryController extends Controller
{
    /**
     * Display a Kanban board of part inquiries.
     */
    public function index(Request $request)
    {
        $status = $request->get('status', 'all');

        $query = PartInquiry::latest('updated_at');

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $inquiries = $query->paginate(30)->withQueryString();

        // For Kanban view, group by status
        $kanban = [];
        foreach (PartInquiryStatus::cases() as $case) {
            $kanban[$case->value] = PartInquiry::where('status', $case->value)
                ->orderBy('created_at')
                ->get();
        }

        return view('admin.cms.inquiries.index', [
            'inquiries' => $inquiries,
            'kanban' => $kanban,
            'statuses' => PartInquiryStatus::cases(),
            'currentStatus' => $status,
        ]);
    }

    /**
     * Display the specified part inquiry.
     */
    public function show(PartInquiry $inquiry)
    {
        return view('admin.cms.inquiries.show', [
            'inquiry' => $inquiry,
        ]);
    }

    /**
     * Update the status of a part inquiry.
     */
    public function updateStatus(Request $request, PartInquiry $inquiry)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(PartInquiryStatus::class)],
            'admin_note' => ['nullable', 'string', 'max:500'],
        ]);

        $inquiry->update([
            'status' => $validated['status'],
            'admin_note' => $validated['admin_note'] ?? $inquiry->admin_note,
        ]);

        return redirect()->route('admin.cms.inquiries.show', $inquiry)
            ->with('success', __('Inquiry status updated successfully.'));
    }

    /**
     * Add a reply to a part inquiry.
     */
    public function addReply(Request $request, PartInquiry $inquiry)
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'send_email' => ['boolean'],
        ]);

        // Move to Reviewing when first reply is given
        if ($inquiry->status === PartInquiryStatus::New) {
            $inquiry->update(['status' => PartInquiryStatus::Reviewing]);
        }

        // TODO: Send email and store reply (Sprint 17)

        return redirect()->route('admin.cms.inquiries.show', $inquiry)
            ->with('success', __('Reply added successfully.'));
    }

    /**
     * Remove the specified part inquiry from storage.
     */
    public function destroy(PartInquiry $inquiry)
    {
        $inquiry->delete();

        return redirect()->route('admin.cms.inquiries.index')
            ->with('success', __('Inquiry deleted successfully.'));
    }

    /**
     * Move inquiry between statuses (for Kanban drag‑and‑drop).
     */
    public function move(Request $request, PartInquiry $inquiry)
    {
        $request->validate([
            'status' => ['required', Rule::enum(PartInquiryStatus::class)],
            'position' => ['nullable', 'integer'],
        ]);

        DB::transaction(function () use ($inquiry, $request) {
            $inquiry->update([
                'status' => $request->status,
            ]);
        });

        return response()->json(['success' => true]);
    }

    /**
     * Export inquiries to CSV.
     */
    public function export(Request $request)
    {
        $inquiries = PartInquiry::all();

        $csv = fopen('php://temp', 'w');
        fputcsv($csv, ['ID', 'Email', 'OEM Number', 'Manufacturer', 'Car Model', 'Status', 'Admin Note', 'Created At']);

        foreach ($inquiries as $inq) {
            fputcsv($csv, [
                $inq->id,
                $inq->email,
                $inq->oem_number,
                $inq->manufacturer,
                $inq->car_model,
                $inq->status->value,
                $inq->admin_note,
                $inq->created_at->format('Y-m-d H:i:s'),
            ]);
        }

        rewind($csv);
        $csvContent = stream_get_contents($csv);
        fclose($csv);

        return response($csvContent, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="part_inquiries_' . date('Y-m-d') . '.csv"',
        ]);
    }
}