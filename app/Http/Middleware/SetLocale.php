<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Normalize locale values like "ar-SA", "ar,en;q=0.9", "AR".
     */
    private function normalizeLocale(?string $rawLocale): ?string
    {
        if ($rawLocale === null) {
            return null;
        }

        // Example header: "ar-SA,ar;q=0.9,en-US;q=0.8,en;q=0.7"
        $first = trim(explode(',', $rawLocale, 2)[0] ?? '');
        $first = trim(explode(';', $first, 2)[0] ?? '');
        if ($first === '') {
            return null;
        }

        // Keep primary subtag only: "ar-SA" -> "ar", "en_US" -> "en"
        $primary = strtolower(trim(preg_split('/[-_]/', $first, 2)[0] ?? ''));
        return $primary !== '' ? $primary : null;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $supportedLocales = ['en', 'ar'];

        // Prefer explicit query param if provided (e.g. ?lang=ar)
        $queryLocale = $this->normalizeLocale($request->query('lang'));
        $headerLocale = $this->normalizeLocale($request->header('Accept-Language'));

        $locale = $queryLocale ?? $headerLocale ?? $this->normalizeLocale(config('app.locale')) ?? 'en';
        if (!in_array($locale, $supportedLocales, true)) {
            $locale = 'en';
        }

        app()->setLocale($locale);
        
        return $next($request);
    }
}
