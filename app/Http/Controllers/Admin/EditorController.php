<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\UploadedImageSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class EditorController extends Controller
{
    public function uploadImage(Request $request, UploadedImageSanitizer $sanitizer): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|image|mimes:jpeg,png,gif,webp|max:2048',
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

        $path = $file->store('editor/' . now()->format('Y/m'), 'public');
        $sanitizer->sanitize('public', $path, $file->getMimeType());

        return response()->json([
            'success' => true,
            'location' => Storage::url($path),
        ]);
    }

    public function previewHtml(Request $request): JsonResponse
    {
        $request->validate(['html' => 'required|string']);

        return response()->json([
            'success' => true,
            'preview' => strip_tags($request->input('html'), '<p><br><strong><em><u><h1><h2><h3><h4><h5><h6><ul><ol><li><a><img><blockquote><pre><code><table><thead><tbody><tr><th><td><div><span>'),
        ]);
    }
}
