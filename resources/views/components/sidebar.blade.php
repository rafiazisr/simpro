@php
    $nav = fn (string ...$patterns) => request()->routeIs(...$patterns) ? 'nav-item active' : 'nav-item';
    $myProjects = auth()->user()->projects()->where('status', '!=', 'done')->orderBy('name')->limit(8)->get(['projects.id', 'code', 'name', 'color']);
    $routeProject = request()->route('project');
    $routeBacklog = request()->route('backlog');
    $currentCode = $routeProject instanceof \App\Models\Project ? $routeProject->code
        : ($routeBacklog instanceof \App\Models\Backlog ? $routeBacklog->project?->code : null);
@endphp
<nav class="sidebar" aria-label="Navigasi utama">
    <div class="brand">
        <span class="t-16" style="flex: 1">{{ config('app.name') }}</span>
        <button class="btn btn-ghost btn-sm btn-icon menu-toggle" type="button" aria-label="Tutup menu" @click="sidebarOpen = false">
            <x-icon name="x" />
        </button>
    </div>

    <a class="{{ $nav('dashboard') }}" href="{{ route('dashboard') }}"><x-icon name="dashboard" />Dashboard</a>
    <a class="{{ request()->routeIs('projects.docs.*') ? 'nav-item' : $nav('projects.*', 'backlogs.*') }}" href="{{ route('projects.index') }}"><x-icon name="folder" />Project</a>
    <a class="{{ $nav('gantt.*') }}" href="{{ route('gantt.index') }}"><x-icon name="gantt" />Timeline</a>
    <a class="{{ $nav('docs.*', 'projects.docs.*') }}" href="{{ route('docs.index') }}"><x-icon name="book" />Dokumentasi</a>

    @can('user.manage')
        <div class="nav-group">Administrasi</div>
        <a class="{{ $nav('users.*') }}" href="{{ route('users.index') }}"><x-icon name="users" />Manajemen User</a>
        <a class="{{ $nav('roles.*') }}" href="{{ route('roles.index') }}"><x-icon name="shield" />Role &amp; Hak Akses</a>
    @endcan

    @if ($myProjects->isNotEmpty())
        <div class="nav-group">Project Saya</div>
        @foreach ($myProjects as $p)
            <a class="nav-item {{ $currentCode === $p->code ? 'active' : '' }}" href="{{ route('projects.show', $p) }}">
                <span style="width: 8px; height: 8px; border-radius: 2px; background: {{ $p->color }}; flex: none"></span>
                <span style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap">{{ $p->name }}</span>
                <span class="count code">{{ $p->code }}</span>
            </a>
        @endforeach
    @endif
</nav>
