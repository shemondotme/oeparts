<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * llms.txt — an unofficial, still-emerging convention (no formal spec the
 * way robots.txt/sitemap.xml have) for giving AI crawlers/answer engines a
 * short, curated overview of the site. Root-level (outside the {lang}
 * locale group), same reasoning as robots.txt/sitemap.xml.
 */
class LlmsTxtController extends Controller
{
    public function index(Request $request): Response
    {
        // The fallback here (not just an empty string) matters: the real
        // default text lives in SettingsSeeder, which only populates the
        // database on installs that actually run it — this route must
        // still return something sensible on a fresh, unseeded database.
        $default = 'OeParts is an OEM auto-parts catalog searchable by manufacturer part number, '
            . "including cross-reference numbers across manufacturers for the same physical part.\n\n"
            . "Sitemap: {site_url}/sitemap.xml";

        $body = settings_trans('crawlers.llms_txt_body', $default);
        $body = str_replace('{site_url}', rtrim(url('/'), '/'), $body);

        return response($body, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            // Same reduced max-age as robots.txt — this is admin-editable
            // (SEO Control Center) and should take effect promptly.
            'Cache-Control' => 'public, max-age=300',
        ]);
    }
}
