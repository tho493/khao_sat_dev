<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;



$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\CheckSurveyStatus::class,
            \Illuminate\Session\Middleware\StartSession::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            'https://khaosatdev.tho493.id.vn/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Throwable $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'error' => true,
                    'message' => $e->getMessage(),
                    'status_code' => $e->getCode() ?: 500
                ], $e->getCode() ?: 500);
            }

            if ($request->is('admin') || $request->is('admin/*')) {
                return null;
            }

            $statusCode = 500;
            if (method_exists($e, 'getStatusCode')) {
                $statusCode = $e->getStatusCode();
            } elseif (method_exists($e, 'getCode')) {
                $statusCode = $e->getCode() ?: 500;
            }

            return response()->view('error', [
                'exception' => $e,
                'statusCode' => $statusCode
            ], $statusCode);
        });
    })
    ->withSchedule(function (Schedule $schedule) {
        $schedule->command('surveys:update-status')->everyFiveMinutes();
        $schedule->command('backup:db --gzip')->dailyAt('02:15');
    })
    ->create();

return $app;
