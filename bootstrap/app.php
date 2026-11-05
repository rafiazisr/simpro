<?php

use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'active' => EnsureUserIsActive::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
        ]);

        $middleware->web(append: [SecurityHeaders::class]);

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // 419: token CSRF tidak cocok (halaman terlalu lama terbuka, atau session
        // berganti karena login/logout di tab lain). Daripada menampilkan halaman
        // "Page Expired", arahkan pengguna kembali dengan pesan yang jelas.
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if ($e->getStatusCode() !== 419 || $request->expectsJson()) {
                return null;
            }

            $input = $request->except(['_token', '_method', 'password', 'password_confirmation', 'current_password']);

            if ($request->routeIs('login.store')) {
                // Sudah login di tab lain: langsung ke dashboard.
                if ($request->user()) {
                    return redirect()->route('dashboard');
                }

                return redirect()->route('login')
                    ->withInput($input)
                    ->with('session_expired', true);
            }

            if (! $request->user()) {
                return redirect()->route('login')->with('session_expired', true);
            }

            return redirect()->back(fallback: route('dashboard'))
                ->withInput($input)
                ->with('toast_warn', 'Halaman sudah terlalu lama terbuka, jadi perubahan belum tersimpan. Silakan kirim ulang.');
        });
    })->create();
