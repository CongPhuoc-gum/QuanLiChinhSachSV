<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Auth\Middleware\Authenticate as AuthenticateMiddleware;
use Illuminate\Support\Facades\Route;

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
        // Ensure unauthenticated API requests don't attempt a web redirect to a 'login' route
        AuthenticateMiddleware::redirectUsing(function ($request) {
            // For API paths or JSON requests, don't redirect — return null so the framework returns 401
            if ($request->is('api/*') || $request->expectsJson()) {
                return null;
            }

            // Fallback: only route to named 'login' if it exists
            return Route::has('login') ? route('login') : null;
        });
    }
}