<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class EditorController extends Controller
{
    public function uploadImage(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|image|mimes:jpeg,png,gif,webp|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $path = $request->file('file')->store('editor', 'public');

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
            'preview' => $request->input('html'),
        ]);
    }
}
