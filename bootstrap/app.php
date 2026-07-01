<?php

use App\Http\Middleware\ValidateApiKey;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*', headers: Request::HEADER_X_FORWARDED_FOR |
            Request::HEADER_X_FORWARDED_HOST |
            Request::HEADER_X_FORWARDED_PORT |
            Request::HEADER_X_FORWARDED_PROTO |
            Request::HEADER_X_FORWARDED_AWS_ELB);
        $middleware->alias([
            'api.key' => ValidateApiKey::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('horizon:snapshot')->everyFiveMinutes();
        $schedule->command('locknear:mark-stale-offline')->everyMinute();
        $schedule->command('locknear:purge-work-order-evidence')->dailyAt('03:15')->withoutOverlapping();
        $schedule->command('locknear:release-provider-no-shows')->everyFiveMinutes()->withoutOverlapping();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
