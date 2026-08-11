<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Models\Manufacturer;
use App\Models\MediaFile;
use App\Models\Page;
use App\Models\SeoMeta;
use App\Services\ImageOptimizationService;
use App\Services\UploadedImageSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class MediaPickerController extends Controller
{
    public function upload(Request $request, UploadedImageSanitizer $sanitizer, ImageOptimizationService $optimizer): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|image|mimes:jpeg,png,gif,webp|max:5120',
            'alt_text' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $file = $request->file('file');

        try {
            $sanitizer->assertSafe($file);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'errors' => ['file' => [$e->getMessage()]]], 422);
        }

        $path = $file->store('media/' . now()->format('Y/m'), 'public');
        $sanitizer->sanitize('public', $path, $file->getMimeType());
        $optimized = $optimizer->optimize('public', $path, $file->getMimeType());

        $media = MediaFile::create([
            'uploaded_by' => Auth::guard('admin')->id(),
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $optimized['path'],
            // Storage::url() alone resolves against config('filesystems.default')
            // (currently 'local', a private disk with no url mapping — see
            // MediaFile::getFileUrlAttribute()), NOT the 'public' disk this file
            // was just stored to two lines up. Explicit disk keeps the stored
            // value correct regardless of what the default disk is set to.
            'file_url' => Storage::disk('public')->url($optimized['path']),
            'mime_type' => $optimized['mime'],
            'size' => $optimized['size'] ?? $file->getSize(),
            'alt_text' => $request->input('alt_text'),
        ]);

        return response()->json([
            'success' => true,
            'file' => $media->toArray(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $query = MediaFile::query();

        if ($search = $request->input('search')) {
            $search = Str::limit($search, 100);
            $search = str_replace(['%', '_'], ['\%', '\_'], $search);
            $query->where(function ($q) use ($search) {
                $q->where('file_name', 'like', "%{$search}%")
                  ->orWhere('alt_text', 'like', "%{$search}%");
            });
        }

        $files = $query->orderBy('id', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $files->toArray(),
            'total' => $files->count(),
        ]);
    }

    public function destroy(MediaFile $media): JsonResponse
    {
        $admin = Auth::guard('admin')->user();
        if (!$admin || $admin->cannot('delete media files')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        // Every FK referencing media_files.id is nullOnDelete() — deleting
        // used-elsewhere media silently blanked out a Page/BlogPost's
        // featured image, a Manufacturer's logo, or a SeoMeta's OG image,
        // with no warning that anything else was depending on this file.
        $usages = [
            'page featured image' => Page::where('featured_image_id', $media->id)->count(),
            'blog post featured image' => BlogPost::where('featured_image_id', $media->id)->count(),
            'manufacturer logo' => Manufacturer::where('logo_id', $media->id)->count(),
            'SEO OG image' => SeoMeta::where('og_image_id', $media->id)->count(),
        ];
        $usages = array_filter($usages);

        if ($usages !== []) {
            $summary = collect($usages)->map(fn ($count, $label) => "{$count} {$label}(s)")->implode(', ');

            return response()->json([
                'success' => false,
                'message' => "This file is still in use: {$summary}. Remove it from those places first.",
            ], 422);
        }

        if ($media->file_path) {
            Storage::disk('public')->delete($media->file_path);
        }

        $media->delete();

        return response()->json(['success' => true]);
    }
}
