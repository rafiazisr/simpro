<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AuthenticatedSessionController extends Controller
{
    /** Endpoint data/latar belakang yang tidak boleh jadi tujuan setelah login. */
    private const NON_PAGE_ROUTES = ['notifications.stream', 'dashboard.data', 'gantt.data', 'gantt.sprint-backlogs'];

    public function create(): Response
    {
        // no-store: tombol Back / tab lama selalu memuat ulang halaman ini,
        // sehingga token CSRF di form selalu yang terbaru.
        return response()->view('auth.login')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();
        $this->forgetNonPageIntended($request);

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /** Buang URL tujuan bila itu endpoint data, bukan halaman yang bisa ditampilkan. */
    private function forgetNonPageIntended(Request $request): void
    {
        $intended = $request->session()->get('url.intended');
        if (! $intended) {
            return;
        }

        try {
            $route = Route::getRoutes()->match(Request::create($intended));
        } catch (HttpException) {
            $route = null;
        }

        if (! $route || in_array($route->getName(), self::NON_PAGE_ROUTES, true)) {
            $request->session()->forget('url.intended');
        }
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
