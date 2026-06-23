<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\ServerMonitorAspect;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
           
            RateLimiter::for('global', function (Request $request) {
                return Limit::perMinute(30)->by('global_pool');
            });

           
            RateLimiter::for('api', function (Request $request) {
                return $request->user()
                    ? Limit::perMinute(30)->by($request->user()->id)
                    : Limit::perMinute(30)->by($request->ip());
            });

           
            RateLimiter::for('orders', function (Request $request) {
                return Limit::perMinute(20)->by($request->user()?->id ?? $request->ip());
            });

         
            RateLimiter::for('checkout', function (Request $request) {
                return Limit::perMinute(5)->by($request->user()?->id ?? $request->ip());
            });
        }
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
        ]);

        $middleware->api(append: [
            
        ]);
        $middleware->append(ServerMonitorAspect::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
