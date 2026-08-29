<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: [
            __DIR__.'/../routes/Api/v1/api.php',
            __DIR__.'/../routes/Api/v2/api.php',
        ],
        apiPrefix: 'system/api',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        [
            'prefix' => 'system/api/v1',
            'middleware' => [
                \App\Http\Middleware\CheckTokenInCookie::class,
                'auth:api',
            ]
        ],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            "check.token_in_cookie" => \App\Http\Middleware\CheckTokenInCookie::class,
        ]);

        $middleware->priority([
            \App\Http\Middleware\CheckTokenInCookie::class,
            // \Iluminate\Auth\Middleware\Authenticate::class,
            'auth:api'
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
