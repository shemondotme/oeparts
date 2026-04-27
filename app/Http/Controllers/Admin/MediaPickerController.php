<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MediaFile;
use Illuminate\Http\Request;

class MediaPickerController extends Controller
{
    /**
     * Get paginated media files (AJAX).
     */
    public function index(Request $request)
    {
        $query = MediaFile::latest('id');

        if ($request->filled('search')) {
            $query->where('file_name', 'like', '%' . $request->search . '%')
                  ->orWhere('alt_text', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('type')) {
            $query->where('mime_type', 'like', $request->type . '%');
        }

        $media = $query->paginate(12);

        return response()->json([
            'success' => true,
            'data'    => $media->items(),
            'total'   => $media->total(),
            'links'   => $media->render(),
        ]);
    }

    /**
     * Upload single media file with drag-drop support.
     */
    public function upload(Request $request)
    {
        $validated = $request->validate([
            'file'     => ['required', 'file', 'mimes:jpeg,png,gif,webp,mp4,pdf', 'max:20480'],
            'alt_text' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $file = $request->file('file');
            $path = $file->store('uploads', 'public');
            $url  = asset('storage/' . $path);

            $media = MediaFile::create([
                'uploaded_by' => auth('admin')->id(),
                'file_name'   => $file->getClientOriginalName(),
                'file_path'   => $path,
                'file_url'    => $url,
                'mime_type'   => $file->getMimeType(),
                'size'        => $file->getSize(),
                'alt_text'    => $validated['alt_text'] ?? '',
            ]);

            return response()->json([
                'success' => true,
                'file'    => $media,
                'message' => 'File uploaded successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Upload failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete media file.
     */
    public function destroy(MediaFile $media)
    {
        try {
            $path = storage_path('app/public/' . $media->file_path);
            if (file_exists($path)) {
                unlink($path);
            }
            $media->delete();

            return response()->json(['success' => true, 'message' => 'File deleted']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
