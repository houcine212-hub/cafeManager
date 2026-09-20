<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reads the `app_locale` cookie (written client-side by resources/js/core/i18n.js
 * whenever the language selector changes) and applies it as the request
 * locale, so server-rendered Blade text (app()->getLocale()) matches the
 * language the visitor picked.
 *
 * Only needed if the app doesn't already set the locale from a cookie or
 * session elsewhere — skip this file if it does.
 */
class SetLocale
{
    private const SUPPORTED = ['fr', 'ar'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->cookie('app_locale');

        if (in_array($locale, self::SUPPORTED, true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}