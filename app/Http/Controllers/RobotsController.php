<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class RobotsController extends Controller
{
    /**
     * Generate dynamic robots.txt content.
     *
     * Rules:
     * - Production: allow all crawlers, link to sitemap
     * - Staging/development: disallow all
     * - Admin area always disallowed
     * - Check settings for additional disallow rules
     */
    public function index(Request $request)
    {
        $lines = [];

        // User-agent rules
        $lines[] = 'User-agent: *';

        // Disallow admin area for all crawlers
        $lines[] = 'Disallow: /admin/';
        $lines[] = 'Disallow: /login';
        $lines[] = 'Disallow: /reset-password';
        $lines[] = 'Disallow: /account/';

        // Allow or disallow based on environment
        if (app()->environment('production')) {
            $lines[] = 'Allow: /';
            $lines[] = 'Allow: /parts/';
            $lines[] = 'Allow: /manufacturers/';
            $lines[] = 'Allow: /blog/';
            $lines[] = 'Allow: /pages/';

            // Disallow checkout and cart pages (sensitive)
            $lines[] = 'Disallow: /checkout/';
            $lines[] = 'Disallow: /cart/';
        } else {
            // Non-production environments: block everything
            $lines[] = 'Disallow: /';
        }

        // Add sitemap reference
        $lines[] = '';
        $lines[] = 'Sitemap: ' . url('sitemap.xml');

        // Add host directive (optional)
        $lines[] = '# Host: ' . $request->getHttpHost();

        $content = implode("\n", $lines);

        return response($content, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}