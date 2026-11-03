<?php

use App\Http\Middleware\PreventResponseCaching;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Log;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // CloudFront などがフォーム HTML をキャッシュすると CSRF で 419 になる
        $middleware->web(append: [
            PreventResponseCaching::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // 既定では報告対象外のため、419 切り分け用にだけ報告する
        $exceptions->stopIgnoring(TokenMismatchException::class);

        $exceptions->reportable(function (TokenMismatchException $e): bool {
            // 個人情報・トークン本体は出さない。CloudFront の Cookie 転送切り分け用。
            Log::warning('CSRF token mismatch', [
                'path' => request()->path(),
                'host' => request()->getHost(),
                'has_session_cookie' => request()->hasCookie((string) config('session.cookie')),
                'sec_fetch_site' => request()->header('Sec-Fetch-Site'),
            ]);

            return false;
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
