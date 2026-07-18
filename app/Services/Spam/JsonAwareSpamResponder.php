<?php

namespace App\Services\Spam;

use Closure;
use Illuminate\Http\Request;
use Spatie\Honeypot\SpamResponder\SpamResponder;

/**
 * The package default (BlankPageResponder) returns an empty body, which is
 * correct for classic <form method="POST"> submissions but breaks every
 * fetch()-based form on the site (contact, auth modal, part-inquiry,
 * newsletter): res.json() on an empty body throws, so a honeypot trip
 * surfaces as a generic "network error" instead of any real message.
 * expectsJson() (driven by the Accept header the fetch() calls already
 * send) distinguishes the two without touching the native-form routes.
 */
class JsonAwareSpamResponder implements SpamResponder
{
    public function respond(Request $request, Closure $next)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => __('part_inquiry.error_generic'),
            ], 422);
        }

        return response('');
    }
}
