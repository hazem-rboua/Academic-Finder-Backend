<?php

namespace App\Providers;

use App\Http\Middleware\TrustedCaller;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // All Twindix API calls come from one server IP: its service key is not limited;
        // everyone else keeps 60 requests a minute (per user, else per IP).
        RateLimiter::for('api', fn (Request $request) => TrustedCaller::isTwindix($request)
            ? Limit::none()
            : Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));
    }
}
