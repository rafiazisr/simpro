@props(['crumbs' => []])
@php $user = auth()->user(); @endphp
<header class="topbar">
    <button class="btn btn-ghost btn-icon menu-toggle" type="button" aria-label="Buka menu" @click="sidebarOpen = true">
        <x-icon name="menu" />
    </button>

    <nav class="breadcrumb grow" aria-label="Breadcrumb">
        @foreach ($crumbs as $label => $url)
            @if ($loop->last)
                <span class="current">{{ is_int($label) ? $url : $label }}</span>
            @else
                @if (is_int($label))
                    <span>{{ $url }}</span>
                @else
                    <a href="{{ $url }}">{{ $label }}</a>
                @endif
                <span class="sep">/</span>
            @endif
        @endforeach
    </nav>

    <a class="btn btn-ghost btn-icon m-only" href="{{ route('search') }}" aria-label="Cari backlog"><x-icon name="search" size="20" /></a>

    <form class="m-hide" method="GET" action="{{ route('search') }}" style="margin: 0" role="search"
          x-data @keydown.ctrl.k.window.prevent="$refs.q.focus()" @keydown.meta.k.window.prevent="$refs.q.focus()">
        <label class="search" aria-label="Cari backlog">
            <x-icon name="search" />
            <input type="search" name="q" x-ref="q" value="{{ request()->routeIs('search') ? request('q') : '' }}" placeholder="Cari backlog (kode atau judul)…">
            <span class="kbd">Ctrl K</span>
        </label>
    </form>

    <x-theme-toggle />

    {{-- Lonceng notifikasi real-time (FR-40 s.d. FR-42) --}}
    @php
        $bell = [
            'items' => $user->notifications()->limit(8)->get()->map(fn ($n) => \App\Support\NotificationPresenter::present($n))->values(),
            'unread' => $user->unreadNotifications()->count(),
            'streamUrl' => route('notifications.stream'),
            'readAllUrl' => route('notifications.read-all'),
            'since' => now()->toIso8601String(),
            'appName' => config('app.name'),
        ];
    @endphp
    <div style="position: relative" x-data="notificationBell(@js($bell))" @click.outside="open = false" @keydown.escape.window="open = false">
        <button class="btn btn-ghost btn-icon bell" type="button" :aria-label="unread ? `Notifikasi, ${unread} belum dibaca` : 'Notifikasi'" :aria-expanded="open"
                :style="open ? 'background: var(--surface-2); color: var(--text)' : ''" @click="open = !open">
            <x-icon name="bell" size="20" />
            <span class="dot-count" x-cloak x-show="unread > 0" x-text="unread > 99 ? '99+' : unread"></span>
        </button>
        <div class="popover notif-pop" x-cloak x-show="open" x-transition.opacity style="position: absolute; right: -8px; top: 46px; width: 400px; max-width: calc(100vw - 32px); padding: 0; z-index: 50">
            <div class="row between" style="padding: 14px 16px; border-bottom: 1px solid var(--border)">
                <span class="t-16">Notifikasi</span>
                <button class="btn btn-ghost btn-sm" type="button" style="color: var(--primary-text)" x-show="unread > 0" @click="readAll()"><x-icon name="check" size="sm" />Tandai semua dibaca</button>
            </div>
            <div class="col" style="padding: 6px; gap: 2px; max-height: 420px; overflow-y: auto">
                <template x-for="n in items" :key="n.id">
                    <a class="notif" :class="n.unread && 'unread'" :href="n.url" style="text-decoration: none; color: inherit">
                        <div :class="iconClass(n.kind)"><x-icon name="bell" /></div>
                        <div class="grow">
                            <div class="n-text" x-html="n.html"></div>
                            <div class="n-time"><span x-text="n.time"></span><template x-if="n.project"><span> · <span x-text="n.project"></span></span></template></div>
                        </div>
                        <span class="unread-dot" x-show="n.unread" aria-label="Belum dibaca"></span>
                    </a>
                </template>
                <template x-if="items.length === 0">
                    <div class="t-12 muted" style="padding: 24px; text-align: center">Belum ada notifikasi.</div>
                </template>
            </div>
            <a class="row link" href="{{ route('notifications.index') }}" style="justify-content: center; height: 44px; border-top: 1px solid var(--border)">Lihat semua notifikasi</a>
        </div>

        {{-- Toast saat notifikasi baru masuk --}}
        <div class="toast-stack is-fixed" x-cloak x-show="toast" x-transition.opacity aria-live="polite">
            <a class="toast" :href="toast?.url" style="text-decoration: none; color: inherit">
                <span class="t-ico" style="color: var(--primary)"><x-icon name="bell" /></span>
                <div class="grow col" style="gap: 2px"><span class="fw-600" x-text="toast?.title"></span><span class="t-12" x-html="toast?.html"></span></div>
            </a>
        </div>

        {{-- Ajakan izin notifikasi browser (layar A03), tampil sekali sampai dijawab --}}
        <div class="card notif-ask" x-cloak x-show="askPermission" x-transition.opacity
             style="position: fixed; left: 24px; bottom: 24px; width: 360px; max-width: calc(100vw - 48px); padding: 16px; z-index: 60; box-shadow: var(--shadow-lg); display: flex; flex-direction: column; gap: 10px">
            <div class="row" style="gap: 12px; align-items: flex-start">
                <div class="modal-icon info"><x-icon name="bell" size="20" /></div>
                <div class="col" style="gap: 4px">
                    <span class="t-16">Aktifkan notifikasi browser?</span>
                    <span class="t-12 muted">Dapatkan pemberitahuan saat Anda di-assign ke project atau backlog, dan saat backlog siap dites, meskipun tab {{ config('app.name') }} sedang tidak dibuka.</span>
                </div>
            </div>
            <div class="row" style="justify-content: flex-end; gap: 8px">
                <button class="btn btn-ghost btn-sm" type="button" @click="dismissPermission()">Nanti saja</button>
                <button class="btn btn-primary btn-sm" type="button" @click="requestPermission()">Izinkan Notifikasi</button>
            </div>
        </div>
    </div>

    <div class="topbar-sep" style="width: 1px; height: 24px; background: var(--border)"></div>

    <div style="position: relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
        <button class="row btn btn-ghost user-btn" type="button" style="height: 40px; padding: 0 6px 0 4px; gap: 10px" aria-label="Menu pengguna" :aria-expanded="open" @click="open = !open">
            <x-avatar :user="$user" />
            <span class="col user-meta" style="align-items: flex-start; line-height: 1.2">
                <span class="fw-600" style="color: var(--text); font-size: 13px">{{ $user->name }}</span>
                <span class="t-12 muted">{{ $user->user_level->label() }}</span>
            </span>
            <x-icon name="chevron-down" size="sm" class="user-chev" />
        </button>
        <div class="popover" x-cloak x-show="open" x-transition.opacity style="position: absolute; right: 0; top: 48px; width: 240px; z-index: 50">
            <div style="padding: 8px 10px">
                <div class="fw-600">{{ $user->name }}</div>
                <div class="t-12 muted">{{ $user->email }}</div>
            </div>
            <div class="menu-sep"></div>
            <form method="POST" action="{{ route('logout') }}" style="margin: 0">
                @csrf
                <button type="submit" class="menu-item danger" style="width: 100%; border: 0; background: none; font: inherit; text-align: left">
                    <x-icon name="logout" />Keluar
                </button>
            </form>
        </div>
    </div>
</header>
