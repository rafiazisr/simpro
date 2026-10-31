<?php

namespace App\Support;

use Illuminate\Notifications\DatabaseNotification;

/** Bentuk data notifikasi yang dipakai lonceng, halaman notifikasi, dan stream SSE. */
class NotificationPresenter
{
    /** @return array<string, mixed> */
    public static function present(DatabaseNotification $n): array
    {
        $d = $n->data;

        return [
            'id' => $n->id,
            'kind' => $d['kind'] ?? 'info',
            'title' => $d['title'] ?? 'Notifikasi',
            'text' => $d['text'] ?? '',
            'html' => self::html($d['text'] ?? '', $d['bold'] ?? []),
            'project' => $d['project'] ?? null,
            'url' => route('notifications.open', $n->id),
            'time' => $n->created_at?->diffForHumans(),
            'created_at' => $n->created_at?->toIso8601String(),
            'unread' => $n->read_at === null,
        ];
    }

    /** Teks di-escape dulu, lalu bagian penting ditebalkan (aman dari XSS). */
    public static function html(string $text, array $bold): string
    {
        $html = e($text);

        foreach (array_unique(array_filter($bold)) as $part) {
            $escaped = e($part);
            $html = preg_replace('/'.preg_quote($escaped, '/').'/u', '<b>'.$escaped.'</b>', $html, 1);
        }

        return $html;
    }

    /** Kelas ikon desain (n-proj, n-assign, n-qa). */
    public static function iconClass(string $kind): string
    {
        return match ($kind) {
            'project' => 'n-ico n-proj',
            'assign' => 'n-ico n-assign',
            'qa' => 'n-ico n-qa',
            default => 'n-ico n-done',
        };
    }
}
