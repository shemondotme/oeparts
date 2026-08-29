<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * IndexNow requires the submitted key to be verifiable by fetching
 * https://{host}/{key}.txt and confirming the file's contents equal the
 * key itself — this route serves that file. Root-level (outside the
 * {lang} locale group), same reasoning as robots.txt/sitemap.xml: the key
 * file must live at the site root, not under a locale prefix.
 */
class IndexNowController extends Controller
{
    public function verify(Request $request, string $key): Response
    {
        $configuredKey = trim((string) settings('seo.indexnow_api_key', ''));

        if ($configuredKey === '' || ! hash_equals($configuredKey, $key)) {
            abort(404);
        }

        return response($configuredKey, 200)->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
