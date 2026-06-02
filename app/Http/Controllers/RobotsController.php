<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class RobotsController extends Controller
{
    /**
     * Generate dynamic robots.txt content.
     *
     * Production rules per PRD § Module 8 — Global SEO Engine:
     *   Disallow: /admin/, /*?sort=, /*?condition=, /*?manufacturer=, /*?page=
     * Non-production: disallow everything.
     */
    public function index(Request $request)
    {
        $lines = ['User-agent: *'];

        if (app()->environment('production')) {
            // Admin and account areas — never crawl
            $lines[] = 'Disallow: /admin/';
            $lines[] = 'Disallow: /*/account/';
            $lines[] = 'Disallow: /*/checkout/';
            $lines[] = 'Disallow: /*/cart/';
            $lines[] = 'Disallow: /*/reset-password/';
            $lines[] = 'Disallow: /install/';

            // Faceted / filter URLs — prevent duplicate-content crawling
            $lines[] = 'Disallow: /*?sort=';
            $lines[] = 'Disallow: /*?condition=';
            $lines[] = 'Disallow: /*?manufacturer=';
            $lines[] = 'Disallow: /*?page=';
            $lines[] = 'Disallow: /*?in_stock=';
            $lines[] = 'Disallow: /*?model=';

            $lines[] = 'Allow: /';
        } else {
            // Staging / development — block all crawlers completely
            $lines[] = 'Disallow: /';
        }

        $lines[] = '';
        $lines[] = 'Sitemap: '.url('sitemap.xml');

        return response(implode("\n", $lines), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
