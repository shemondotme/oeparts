<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\UploadedImageSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Mews\Purifier\Facades\Purifier;

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

        // strip_tags() only filters TAG names — it leaves every attribute on
        // an allowed tag untouched, so `<img src=x onerror=alert(1)>` or
        // `<a href="javascript:...">` passed straight through into the JSON
        // response. HTMLPurifier (already a project dependency, used with
        // the same 'default' profile as other rich-text content) strips
        // dangerous attributes/protocols too, not just disallowed tags.
        return response()->json([
            'success' => true,
            'preview' => Purifier::clean($request->input('html'), 'default'),
        ]);
    }
}
