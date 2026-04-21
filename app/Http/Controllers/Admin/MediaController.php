<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MediaFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaController extends Controller
{
    /**
     * Display a paginated list of media files with filters.
     */
    public function index(Request $request)
    {
        $query = MediaFile::latest();

        // Filter by type
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // Search by filename
        if ($request->filled('search')) {
            $query->where('filename', 'like', '%' . $request->search . '%')
                ->orWhere('original_name', 'like', '%' . $request->search . '%');
        }

        $media = $query->paginate(30)->withQueryString();

        return view('admin.cms.media.index', [
            'media' => $media,
        ]);
    }

    /**
     * Show the form for uploading new media.
     */
    public function create()
    {
        return view('admin.cms.media.create');
    }

    /**
     * Store uploaded media files.
     */
    public function store(Request $request)
    {
        $request->validate([
            'files.*' => ['required', 'file', 'max:10240'], // 10MB max
            'alt_text' => ['nullable', 'array'],
            'alt_text.*' => ['nullable', 'string', 'max:255'],
            'caption' => ['nullable', 'array'],
            'caption.*' => ['nullable', 'string', 'max:500'],
        ]);

        $uploaded = [];

        foreach ($request->file('files') as $index => $file) {
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $filename = Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) . '-' . time() . '.' . $extension;
            $path = $file->storeAs('media', $filename, 'public');

            // Determine MIME type
            $mime = $file->getMimeType();
            $type = str_starts_with($mime, 'image/') ? 'image' : 'document';

            // Thumbnail generation requires intervention/image (not installed); skip for now
            $thumbnailPath = null;

            $media = MediaFile::create([
                'filename' => $filename,
                'original_name' => $originalName,
                'path' => $path,
                'thumbnail_path' => $thumbnailPath,
                'type' => $type,
                'mime_type' => $mime,
                'size' => $file->getSize(),
                'alt_text' => $request->alt_text[$index] ?? null,
                'caption' => $request->caption[$index] ?? null,
                'uploaded_by' => auth('admin')->id(),
            ]);

            $uploaded[] = $media;
        }

        if ($request->expectsJson()) {
            return response()->json(['files' => $uploaded]);
        }

        return redirect()->route('admin.cms.media.index')
            ->with('success', __('Files uploaded successfully.'));
    }

    /**
     * Display the specified media file.
     */
    public function show(MediaFile $media)
    {
        return view('admin.cms.media.show', [
            'media' => $media,
        ]);
    }

    /**
     * Show the form for editing media metadata.
     */
    public function edit(MediaFile $media)
    {
        return view('admin.cms.media.edit', [
            'media' => $media,
        ]);
    }

    /**
     * Update the specified media metadata.
     */
    public function update(Request $request, MediaFile $media)
    {
        $validated = $request->validate([
            'alt_text' => ['nullable', 'array'],
            'alt_text.*' => ['nullable', 'string', 'max:255'],
            'caption' => ['nullable', 'array'],
            'caption.*' => ['nullable', 'string', 'max:500'],
        ]);

        $media->update($validated);

        return redirect()->route('admin.cms.media.index')
            ->with('success', __('Media updated successfully.'));
    }

    /**
     * Remove the specified media file from storage.
     */
    public function destroy(MediaFile $media)
    {
        // Delete physical files
        Storage::disk('public')->delete($media->path);
        if ($media->thumbnail_path) {
            Storage::disk('public')->delete($media->thumbnail_path);
        }

        $media->delete();

        return redirect()->route('admin.cms.media.index')
            ->with('success', __('Media deleted successfully.'));
    }

}