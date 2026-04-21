<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    protected array $supported = ['en', 'de', 'lt', 'fr', 'es'];

    public function handle(Request $request, Closure $next): Response
    {
        $lang = $request->route('lang');

        if ($lang && in_array($lang, $this->supported)) {
            app()->setLocale($lang);
        }

        return $next($request);
    }
}
