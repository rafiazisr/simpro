<x-layouts.app title="Manajemen User" :crumbs="['Administrasi', 'Manajemen User']">
    <div class="page-head">
        <div>
            <h1 class="t-24">Manajemen User</h1>
        </div>
        @can('create', App\Models\User::class)
            <a class="btn btn-primary" href="{{ route('users.create', request()->query()) }}"><x-icon name="plus" />Tambah User</a>
        @endcan
    </div>

    <div class="card">
        <form class="toolbar" method="GET" action="{{ route('users.index') }}" style="padding: 12px 16px; border-bottom: 1px solid var(--border)">
            <div class="input-icon" style="width: 300px; max-width: 100%">
                <x-icon name="search" />
                <input class="input" type="search" name="q" value="{{ request('q') }}" placeholder="Cari nama atau email…" aria-label="Cari user">
            </div>
            <select class="select" name="level" style="width: 170px" aria-label="Filter level" onchange="this.form.submit()">
                <option value="">Semua level</option>
                @foreach ($levels as $level)
                    <option value="{{ $level->value }}" @selected(request('level') === $level->value)>{{ $level->label() }}</option>
                @endforeach
            </select>
            <select class="select" name="status" style="width: 160px" aria-label="Filter status" onchange="this.form.submit()">
                <option value="">Semua status</option>
                <option value="aktif" @selected(request('status') === 'aktif')>Aktif</option>
                <option value="nonaktif" @selected(request('status') === 'nonaktif')>Nonaktif</option>
            </select>
            @if (request()->hasAny(['q', 'level', 'status']))
                <a class="btn btn-ghost btn-sm" href="{{ route('users.index') }}">Reset</a>
            @endif
            <span class="grow"></span>
            <span class="t-12 muted">{{ $totalUsers }} user · {{ $activeUsers }} aktif</span>
        </form>

        @if ($users->isEmpty())
            <div class="empty" style="min-height: 280px; justify-content: center">
                <h3 class="t-16">Tidak ada user yang cocok</h3>
                <p>Coba kata kunci atau filter lain.</p>
            </div>
        @else
            @php
                $sortLink = fn (string $col) => route('users.index', array_merge(request()->query(), [
                    'sort' => $col,
                    'dir' => $sort === $col && $dir === 'asc' ? 'desc' : 'asc',
                ]));
                $sortCls = fn (string $col) => 'sort'.($sort === $col ? ' '.$dir : '');
            @endphp
            <div class="table-wrap">
                <table class="table hover table-cards">
                    <thead>
                    <tr>
                        <th class="{{ $sortCls('name') }}"><a href="{{ $sortLink('name') }}">Nama<span class="sort-ico"><b></b><b></b></span></a></th>
                        <th>Level</th>
                        <th class="num">Project</th>
                        <th>Status</th>
                        <th class="{{ $sortCls('last_login_at') }}"><a href="{{ $sortLink('last_login_at') }}">Terakhir login<span class="sort-ico"><b></b><b></b></span></a></th>
                        <th style="width: 96px"><span class="sr-only">Aksi</span></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($users as $u)
                        <tr>
                            <td>
                                <div class="row" style="gap: 12px">
                                    <x-avatar :user="$u" />
                                    <div class="col" style="line-height: 1.3">
                                        <span class="fw-500" style="color: var(--text)">{{ $u->name }}@if (auth()->user()->is($u)) <span class="t-12 muted">(Anda)</span>@endif</span>
                                        <span class="t-12 muted">{{ $u->email }}</span>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Level"><span class="{{ $u->user_level->badgeClass() }}">{{ $u->user_level->label() }}</span></td>
                            <td class="num" data-label="Project">@if ($u->projects_count){{ $u->projects_count }}@else<span class="muted">—</span>@endif</td>
                            <td data-label="Status">
                                @can('toggleActive', $u)
                                    @if ($u->is_active)
                                        <label class="toggle toggle-success">
                                            <input type="checkbox" checked aria-label="Status aktif {{ $u->name }}"
                                                   @click.prevent="$dispatch('confirm', {
                                                       title: @js('Nonaktifkan '.$u->name.'?'),
                                                       message: '<b style=\'color: var(--text)\'>Sesi user akan langsung diputus</b> dan ia tidak bisa login sampai diaktifkan kembali. Backlog yang di-assign tetap tersimpan.',
                                                       action: @js(route('users.toggle-active', $u)),
                                                       method: 'PATCH',
                                                       confirmLabel: 'Ya, nonaktifkan'
                                                   })">
                                            <span class="t-12" style="color: var(--text-2)">Aktif</span>
                                        </label>
                                    @else
                                        <form class="inline" method="POST" action="{{ route('users.toggle-active', $u) }}">
                                            @csrf @method('PATCH')
                                            <label class="toggle toggle-success">
                                                <input type="checkbox" aria-label="Aktifkan {{ $u->name }}" onchange="this.form.submit()">
                                                <span class="t-12" style="color: var(--text-3)">Nonaktif</span>
                                            </label>
                                        </form>
                                    @endif
                                @else
                                    <label class="toggle toggle-success" title="{{ $u->isSuperAdmin() ? 'Super Admin tidak dapat dinonaktifkan' : 'Anda tidak bisa menonaktifkan akun sendiri' }}">
                                        <input type="checkbox" @checked($u->is_active) disabled aria-label="Status aktif {{ $u->name }}">
                                        <span class="t-12" style="color: var(--text-2)">{{ $u->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                                    </label>
                                @endcan
                            </td>
                            <td class="muted" data-label="Terakhir login">{{ $u->last_login_at?->translatedFormat('d M Y, H.i') ?? 'Belum pernah' }}</td>
                            <td class="actions">
                                <div class="row" style="gap: 2px; justify-content: flex-end">
                                    @can('update', $u)
                                        <a class="btn btn-ghost btn-sm btn-icon" href="{{ route('users.edit', [$u] + request()->query()) }}" aria-label="Ubah {{ $u->name }}"><x-icon name="edit" /></a>
                                    @endcan
                                    @can('delete', $u)
                                        <button class="btn btn-ghost btn-sm btn-icon" type="button" aria-label="Hapus {{ $u->name }}"
                                                @click="$dispatch('confirm', {
                                                    title: @js('Hapus '.$u->name.'?'),
                                                    message: 'Akun akan dihapus dan sesinya diputus. Data backlog yang terkait tetap tersimpan.',
                                                    action: @js(route('users.destroy', $u)),
                                                    method: 'DELETE',
                                                    confirmLabel: 'Ya, hapus'
                                                })"><x-icon name="trash" /></button>
                                    @else
                                        <button class="btn btn-ghost btn-sm btn-icon" type="button" disabled aria-label="Hapus {{ $u->name }} tidak diizinkan"><x-icon name="trash" /></button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            {{ $users->links() }}
        @endif
    </div>

    @if ($modal === 'form')
        @include('users.partials.form-modal', ['editing' => $editing])
    @endif
</x-layouts.app>
