<?php

namespace App\Http\Controllers;

use App\Support\NotificationPresenter;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $onlyUnread = $request->query('filter') === 'unread';
        $user = $request->user();

        $notifications = ($onlyUnread ? $user->unreadNotifications() : $user->notifications())
            ->paginate(20)->withQueryString();

        return view('notifications.index', [
            'notifications' => $notifications,
            'items' => collect($notifications->items())->map(fn ($n) => NotificationPresenter::present($n)),
            'onlyUnread' => $onlyUnread,
            'unreadCount' => $user->unreadNotifications()->count(),
        ]);
    }

    public function open(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        $url = $notification->data['url'] ?? route('dashboard');

        // Hanya URL internal (relatif) yang diikuti.
        return redirect()->to(str_starts_with($url, '/') && ! str_starts_with($url, '//') ? $url : route('dashboard'));
    }

    public function readAll(Request $request): JsonResponse|RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return $request->expectsJson()
            ? response()->json(['unread' => 0])
            : back()->with('toast', 'Semua notifikasi ditandai sudah dibaca.');
    }

    public function stream(Request $request): StreamedResponse
    {
        $user = $request->user();
        $since = $request->header('Last-Event-ID') ?: $request->query('since');

        try {
            $since = $since ? Carbon::parse($since) : now();
        } catch (\Throwable) {
            $since = now();
        }

        // Lepaskan sesi lebih awal; stream tidak menulis ke sesi.
        $request->session()->save();

        $duration = php_sapi_name() === 'cli-server' ? 0 : (int) config('settings.sse.duration', 20);

        return response()->stream(function () use ($user, $since, $duration) {
            $deadline = microtime(true) + $duration;
            $sent = [];

            echo "retry: 3000\n\n";

            while (true) {
                $items = $user->notifications()
                    ->where('created_at', '>=', $since)
                    ->reorder('created_at')->limit(20)->get();

                foreach ($items as $n) {
                    if (isset($sent[$n->id])) {
                        continue;
                    }
                    $sent[$n->id] = true;

                    echo 'id: '.$n->created_at->toIso8601String()."\n";
                    echo "event: notification\n";
                    echo 'data: '.json_encode(NotificationPresenter::present($n))."\n\n";
                }

                echo "event: unread\n";
                echo 'data: '.$user->unreadNotifications()->count()."\n\n";

                if (! app()->runningUnitTests()) {
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                }

                if (connection_aborted() || microtime(true) >= $deadline) {
                    break;
                }

                sleep(2);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
        ]);
    }
}
