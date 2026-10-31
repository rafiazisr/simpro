<x-layouts.app title="Role & Hak Akses" :crumbs="['Administrasi', 'Role & Hak Akses']">
    <form id="matrix-form" method="POST" action="{{ route('roles.matrix') }}">
        @csrf @method('PUT')
        <input type="hidden" name="selected" value="{{ $selected?->name }}">
    </form>

    <div class="page-head">
        <div>
            <h1 class="t-24">Role &amp; Hak Akses</h1>
        </div>
        <div class="row" x-data="{ adding: {{ $errors->has('label') ? 'true' : 'false' }} }">
            <button class="btn btn-secondary" type="button" @click="adding = true" x-show="!adding"><x-icon name="plus" />Role Baru</button>
            <form method="POST" action="{{ route('roles.store') }}" class="row wrap" style="gap: 8px" x-show="adding" x-cloak>
                @csrf
                <div class="field" style="gap: 4px">
                    <input class="input @error('label') is-invalid @enderror" name="label" value="{{ old('label') }}" placeholder="Nama role, mis. Tech Lead" aria-label="Nama role baru" style="width: 220px">
                    <x-field-error name="label" />
                </div>
                <button class="btn btn-secondary" type="submit">Buat</button>
                <button class="btn btn-ghost" type="button" @click="adding = false">Batal</button>
            </form>
            <button class="btn btn-primary" type="submit" form="matrix-form">Simpan Perubahan</button>
        </div>
    </div>

    <div style="display: flex; gap: 20px; align-items: flex-start; flex-wrap: wrap">
        <div class="card m-full" style="flex: 0 0 260px">
            <div class="menu-label" style="padding: 14px 14px 6px">Level user</div>
            <div class="col" style="padding: 0 6px 6px; gap: 2px">
                @foreach ($levelRoles as $r)
                    <a class="menu-item {{ $selected?->is($r) ? 'is-active selected' : '' }}" href="{{ route('roles.index', ['role' => $r->name]) }}" @if ($selected?->is($r)) style="background: var(--primary-soft)" @endif>
                        @if ($r->name === 'super_admin')<x-icon name="lock" />@endif
                        {{ $r->label ?? $r->name }}<span class="meta">{{ $userCounts[$r->id] ?? 0 }}</span>
                    </a>
                @endforeach
            </div>
            <div class="divider"></div>
            <div class="menu-label" style="padding: 14px 14px 6px">Role project</div>
            <div class="col" style="padding: 0 6px 8px; gap: 2px">
                @foreach ($projectRoles as $r)
                    <div class="row" style="gap: 2px">
                        <a class="menu-item grow {{ $selected?->is($r) ? 'is-active selected' : '' }}" href="{{ route('roles.index', ['role' => $r->name]) }}" @if ($selected?->is($r)) style="background: var(--primary-soft)" @endif>
                            {{ $r->label ?? $r->name }}
                        </a>
                        @unless ($r->is_system)
                            <button class="btn btn-ghost btn-sm btn-icon" type="button" aria-label="Hapus role {{ $r->label }}"
                                    @click="$dispatch('confirm', {
                                        title: @js('Hapus role '.($r->label ?? $r->name).'?'),
                                        message: 'Role dan seluruh hak aksesnya akan dihapus.',
                                        action: @js(route('roles.destroy', $r)),
                                        method: 'DELETE',
                                        confirmLabel: 'Ya, hapus'
                                    })"><x-icon name="trash" /></button>
                        @endunless
                    </div>
                @endforeach
            </div>
        </div>

        <div class="card" style="flex: 999 1 640px; min-width: 0">
            <div class="card-head">
                <div>
                    <span class="t-16">Matriks Permission</span>
                    <p class="t-12 muted">Super Admin selalu punya semua akses.</p>
                </div>
                <span class="badge" style="background: var(--warning-soft); color: var(--warning-text)"
                      x-data x-cloak x-show="$store.matrix.dirty">Ada perubahan belum disimpan</span>
            </div>
            <div class="table-wrap">
                <table class="table matrix" style="min-width: {{ 260 + 96 * $roles->count() }}px">
                    <thead>
                    <tr>
                        <th style="width: 260px">Permission</th>
                        @foreach ($roles as $r)
                            <th style="text-align: center; width: 96px; {{ $selected?->is($r) ? 'background: var(--primary-soft); color: var(--primary-text)' : '' }}">
                                {{ $r->name === 'pm' ? 'PM' : ($r->label ?? $r->name) }}
                            </th>
                        @endforeach
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($groups as $groupName => $perms)
                        <tr class="group"><td colspan="{{ $roles->count() + 1 }}">{{ $groupName }}</td></tr>
                        @foreach ($perms as $permName => [$permLabel])
                            <tr>
                                <td style="padding-left: 28px; color: var(--text)">{{ $permLabel }}<div class="code" style="font-weight: 400">{{ $permName }}</div></td>
                                @foreach ($roles as $r)
                                    @php $locked = $r->name === 'super_admin'; @endphp
                                    <td style="text-align: center; {{ $selected?->is($r) ? 'background: var(--primary-soft)' : '' }}">
                                        <label class="check" style="justify-content: center">
                                            <input type="checkbox" form="matrix-form"
                                                   name="matrix[{{ $r->name }}][]" value="{{ $permName }}"
                                                   @checked($locked || in_array($permName, $granted[$r->name] ?? [], true))
                                                   @disabled($locked)
                                                   @change="$store.matrix.dirty = true"
                                                   aria-label="{{ $permLabel }}: {{ $r->label ?? $r->name }}">
                                        </label>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-layouts.app>
