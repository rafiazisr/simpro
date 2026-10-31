<x-layouts.app title="Notifikasi" :crumbs="['Notifikasi']">
    <div class="page-head">
        <div>
            <h1 class="t-24">Notifikasi</h1>
            <p class="sub">{{ $unreadCount }} belum dibaca</p>
        </div>
        @if ($unreadCount)
            <form method="POST" action="{{ route('notifications.read-all') }}" style="margin: 0">
                @csrf
                <button class="btn btn-secondary" type="submit"><x-icon name="check" />Tandai semua dibaca</button>
            </form>
        @endif
    </div>

    <div class="toolbar">
        <div class="seg" role="group" aria-label="Filter notifikasi">
            <a class="{{ $onlyUnread ? '' : 'on' }}" href="{{ route('notifications.index') }}">Semua</a>
            <a class="{{ $onlyUnread ? 'on' : '' }}" href="{{ route('notifications.index', ['filter' => 'unread']) }}">Belum dibaca</a>
        </div>
    </div>

    <div class="card">
        @if ($items->isEmpty())
            <div class="empty" style="min-height: 240px; justify-content: center">
                <div class="modal-icon info"><x-icon name="bell" size="20" /></div>
                <h3 class="t-16">{{ $onlyUnread ? 'Semua notifikasi sudah dibaca' : 'Belum ada notifikasi' }}</h3>
                <p>Notifikasi muncul saat Anda di-assign ke project atau backlog, dan saat backlog siap dites.</p>
            </div>
        @else
            <div class="col" style="padding: 6px; gap: 2px">
                @foreach ($items as $n)
                    <a class="notif {{ $n['unread'] ? 'unread' : '' }}" href="{{ $n['url'] }}" style="text-decoration: none; color: inherit">
                        <div class="{{ \App\Support\NotificationPresenter::iconClass($n['kind']) }}"><x-icon name="bell" /></div>
                        <div class="grow">
                            <div class="n-text">{!! $n['html'] !!}</div>
                            <div class="n-time">{{ $n['time'] }}@if ($n['project']) · {{ $n['project'] }}@endif</div>
                        </div>
                        @if ($n['unread'])<span class="unread-dot" aria-label="Belum dibaca"></span>@endif
                    </a>
                @endforeach
            </div>
            {{ $notifications->links() }}
        @endif
    </div>
</x-layouts.app>
