@props(['title' => null, 'crumbs' => []])
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    <x-theme-script />
    <x-favicon />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="pt">
<div class="app" x-data="{ sidebarOpen: false }">
    <div class="side-backdrop" :class="{ open: sidebarOpen }" @click="sidebarOpen = false" x-cloak></div>
    <div class="app-side" :class="{ open: sidebarOpen }" @keydown.escape.window="sidebarOpen = false">
        <x-sidebar />
    </div>

    <div class="app-main">
        <x-topbar :crumbs="$crumbs" />
        <main class="content">
            {{ $slot }}
        </main>
    </div>
</div>

@if (session('toast'))
    <div class="toast-stack is-fixed" aria-live="polite">
        <div class="toast success" x-data="toast" x-show="show" x-transition.opacity>
            <span class="t-ico"><x-icon name="check" /></span>
            <div class="grow">{{ session('toast') }}</div>
            <button class="btn btn-ghost btn-sm btn-icon" type="button" aria-label="Tutup" @click="show = false"><x-icon name="x" size="sm" /></button>
        </div>
    </div>
@endif

<div x-data="confirmDialog" @confirm.window="show($event)" @keydown.escape.window="open = false">
    <template x-if="open">
        <div>
            <div class="backdrop is-fixed" @click="open = false"></div>
            <div class="modal is-fixed" role="alertdialog" aria-labelledby="cd-title" aria-describedby="cd-desc" style="top: 160px; width: 480px">
                <div class="modal-head">
                    <div class="row" style="gap: 14px; align-items: flex-start">
                        <div class="modal-icon danger"><x-icon name="ban" size="20" /></div>
                        <div class="col" style="gap: 6px">
                            <h2 id="cd-title" class="t-16" x-text="title"></h2>
                            <p id="cd-desc" class="muted" x-html="message"></p>
                        </div>
                    </div>
                </div>
                <template x-if="detail">
                    <div class="modal-body" style="padding-top: 16px">
                        <div class="alert alert-warn" style="padding: 10px 12px"><x-icon name="alert-triangle" style="margin-top: 2px" /><span class="t-12" x-text="detail"></span></div>
                    </div>
                </template>
                <form class="modal-foot" method="POST" :action="action">
                    @csrf
                    <input type="hidden" name="_method" :value="method">
                    <button class="btn btn-secondary" type="button" x-ref="cancel" @click="open = false">Batal</button>
                    <button class="btn btn-danger" type="submit" x-text="confirmLabel"></button>
                </form>
            </div>
        </div>
    </template>
</div>
@stack('scripts')
</body>
</html>
