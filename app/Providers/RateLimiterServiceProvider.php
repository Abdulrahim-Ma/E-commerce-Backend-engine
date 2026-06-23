<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class RateLimiterServiceProvider extends ServiceProvider
{
    
    public function register(): void
    {

    }

    
    public function boot(): void
    {
      
        RateLimiter::for('global', function (Request $request) {
            return Limit::perMinute(100)->by('global');
        });

        
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        
        RateLimiter::for('orders', function (Request $request) {
            $userId = $request->user()?->id ?: $request->ip();
            return [
                Limit::perMinute(20)->by('orders:user:' . $userId),
                Limit::perMinute(200)->by('orders:global'),
            ];
        });

       
        RateLimiter::for('checkout', function (Request $request) {
            $userId = $request->user()?->id ?: $request->ip();
            return [
                Limit::perMinute(5)->by('checkout:user:' . $userId),
                Limit::perMinute(30)->by('checkout:global'),
            ];
        });
    }
}
