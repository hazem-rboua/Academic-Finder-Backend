<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Only trusted callers reach the exam / report endpoints:
 *   - the Twindix API, with X-Service-Key equal to TWINDIX_SERVICE_KEY (constant-time compare), or
 *   - a user logged in to this app (Sanctum), for its own company product.
 * Anyone else gets 401. With no TWINDIX_SERVICE_KEY set, only logged-in users pass.
 */
class TrustedCaller
{
    public function handle(Request $request, Closure $next): Response
    {
        if (self::isTwindix($request) || (self::carriesLogin($request) && auth('sanctum')->check())) {
            return $next($request);
        }

        return response()->json(['success' => false, 'message' => 'Unauthorized.'], 401);
    }

    /** A token or a session cookie: only then is it worth asking Sanctum (anonymous calls are refused at once). */
    private static function carriesLogin(Request $request): bool
    {
        return $request->bearerToken() !== null || $request->cookies->count() > 0;
    }

    public static function isTwindix(Request $request): bool
    {
        $key = (string) config('services.twindix.key');

        return $key !== '' && hash_equals($key, (string) $request->header('X-Service-Key'));
    }
}
