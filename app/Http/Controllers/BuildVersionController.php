<?php

namespace App\Http\Controllers;

use App\Support\AppBuildId;
use Illuminate\Http\JsonResponse;

class BuildVersionController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'build' => AppBuildId::current(),
        ]);
    }
}
