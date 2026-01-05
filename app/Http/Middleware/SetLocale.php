<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get locale from Accept-Language header or query parameter
        $locale = $request->header('Accept-Language') ?? $request->query('lang', config('app.locale'));
        
        // Validate locale
        $supportedLocales = ['en', 'ar'];
        if (!in_array($locale, $supportedLocales)) {
            $locale = config('app.locale', 'en');
        }
        
        // Set application locale
        app()->setLocale($locale);
        
        return $next($request);
    }
}
