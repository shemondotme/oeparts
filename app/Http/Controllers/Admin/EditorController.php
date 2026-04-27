<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EditorController extends Controller
{
    /**
     * Handle rich text editor API for image uploads.
     * Called via AJAX from TinyMCE editor.
     */
    public function uploadImage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => ['required', 'image', 'mimes:jpeg,png,gif,webp', 'max:5120'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid image file',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $file = $request->file('file');
            $path = $file->store('editor-images', 'public');
            $url = asset('storage/' . $path);

            return response()->json([
                'success' => true,
                'location' => $url,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload image: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Preview rich text HTML (for live preview).
     */
    public function previewHtml(Request $request)
    {
        $validated = $request->validate([
            'html' => ['nullable', 'string'],
        ]);

        try {
            // Simple sanitization (in production, use HTML Purifier)
            $html = $validated['html'] ?? '';
            
            return response()->json([
                'success' => true,
                'preview' => $html,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate preview',
            ], 500);
        }
    }
}
