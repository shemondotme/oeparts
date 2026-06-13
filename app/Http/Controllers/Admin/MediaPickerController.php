<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MediaFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class MediaPickerController extends Controller
{
    public function upload(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|image|mimes:jpeg,png,gif,webp|max:5120',
            'alt_text' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $file = $request->file('file');
        $path = $file->store('media', 'public');

        $media = MediaFile::create([
            'uploaded_by' => Auth::guard('admin')->id(),
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'file_url' => Storage::url($path),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
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

        if ($media->file_path) {
            Storage::disk('public')->delete($media->file_path);
        }

        $media->delete();

        return response()->json(['success' => true]);
    }
}
