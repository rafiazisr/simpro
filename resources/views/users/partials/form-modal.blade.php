@php
    $isEdit = $editing !== null;
    $backUrl = route('users.index', request()->query());
    $levelValue = old('user_level', $editing?->user_level->value ?? 'user');
    $activeValue = old('_token') ? (bool) old('is_active') : ($editing?->is_active ?? true);
@endphp
<div class="backdrop is-fixed" onclick="window.location='{{ $backUrl }}'"></div>
<form class="modal is-fixed" role="dialog" aria-labelledby="fu-title" style="top: 48px; width: 680px"
      method="POST" action="{{ $isEdit ? route('users.update', $editing) : route('users.store') }}" novalidate
      x-data @keydown.escape.window="window.location = @js($backUrl)">
    @csrf
    @if ($isEdit) @method('PUT') @endif

    <div class="modal-head">
        <div>
            <h2 id="fu-title" class="t-20">{{ $isEdit ? 'Ubah User' : 'Tambah User' }}</h2>
            <p class="muted t-12" style="margin-top: 2px">Field bertanda <span style="color: var(--danger)">*</span> wajib diisi.</p>
        </div>
        <a class="btn btn-ghost btn-sm btn-icon" href="{{ $backUrl }}" aria-label="Tutup"><x-icon name="x" /></a>
    </div>

    <div class="modal-body">
        @if ($errors->any())
            <div class="alert alert-danger" role="alert" style="padding: 10px 12px">
                <x-icon name="alert-circle" style="margin-top: 2px" />
                <span>Ada {{ $errors->count() }} isian yang perlu diperbaiki.</span>
            </div>
        @endif

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px">
            <div class="field">
                <label class="label" for="u-name">Nama lengkap <span class="req">*</span></label>
                <input id="u-name" name="name" class="input @error('name') is-invalid @enderror" value="{{ old('name', $editing?->name) }}" required autofocus>
                <x-field-error name="name" />
            </div>
            <div class="field">
                <label class="label" for="u-email">Email <span class="req">*</span></label>
                <input id="u-email" name="email" type="email" class="input @error('email') is-invalid @enderror" value="{{ old('email', $editing?->email) }}" required>
                <x-field-error name="email" />
            </div>
            <div class="field">
                <label class="label" for="u-pw">Password @unless ($isEdit)<span class="req">*</span>@endunless</label>
                <input id="u-pw" name="password" type="password" class="input @error('password') is-invalid @enderror" autocomplete="new-password" @unless ($isEdit) required @endunless>
                @error('password')
                    <x-field-error name="password" />
                @else
                    <span class="help">{{ $isEdit ? 'Kosongkan jika tidak ingin mengganti password.' : 'Minimal 8 karakter.' }}</span>
                @enderror
            </div>
            <div class="field">
                <label class="label" for="u-lvl">User level <span class="req">*</span></label>
                <select id="u-lvl" name="user_level" class="select @error('user_level') is-invalid @enderror">
                    @foreach ($assignableLevels as $level)
                        <option value="{{ $level->value }}" @selected($levelValue === $level->value)>{{ $level->label() }}</option>
                    @endforeach
                </select>
                @error('user_level')
                    <x-field-error name="user_level" />
                @else
                    <span class="help">Super Admin hanya bisa ditetapkan lewat seeder.</span>
                @enderror
            </div>
        </div>

        @if ($isEdit && auth()->user()->is($editing))
            <span class="t-12 muted">Anda tidak bisa menonaktifkan akun sendiri.</span>
            <input type="hidden" name="is_active" value="1">
        @else
            <label class="toggle toggle-success">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" @checked($activeValue)>
                <span class="fw-500">Status aktif</span><span class="t-12 muted">— user bisa login segera</span>
            </label>
        @endif

        <div class="divider"></div>
        @php
            $assignRows = old('projects_sync') ? array_values(old('projects', [])) : $memberRows->all();
            $assignErrors = collect($errors->get('projects'))->merge(collect($errors->get('projects.*'))->flatten())->unique();
        @endphp
        <section class="col" style="gap: 12px" x-data="{ rows: @js($assignRows) }">
            <input type="hidden" name="projects_sync" value="1">
            <div class="row between wrap" style="gap: 8px; align-items: flex-start">
                <div class="col" style="gap: 2px">
                    <h3 class="t-16">Assign ke Project</h3>
                    <p class="t-12 muted">Role project (PM / Programmer / QA) menentukan hak akses user di project itu. User mendapat notifikasi saat ditambahkan.</p>
                </div>
                @if ($assignableProjects->isNotEmpty())
                    <button class="btn btn-secondary btn-sm" type="button" @click="rows.push({ project_id: '', role: 'programmer' })"><x-icon name="plus" size="sm" />Tambah project</button>
                @endif
            </div>

            @if ($assignErrors->isNotEmpty())
                <div class="alert alert-danger" role="alert" style="padding: 10px 12px">
                    <x-icon name="alert-circle" style="margin-top: 2px" />
                    <div class="col" style="gap: 2px">@foreach ($assignErrors as $msg)<span class="t-12">{{ $msg }}</span>@endforeach</div>
                </div>
            @endif

            @if ($assignableProjects->isEmpty())
                <p class="t-12 muted">Belum ada project yang bisa Anda atur. Buat project dulu di menu Project.</p>
            @else
                <div class="col" style="gap: 8px">
                    <template x-for="(row, i) in rows" :key="i">
                        <div class="assign-row">
                            <select class="select" :name="`projects[${i}][project_id]`" x-model="row.project_id" :aria-label="`Project baris ${i + 1}`">
                                <option value="">Pilih project…</option>
                                @foreach ($assignableProjects as $p)
                                    <option value="{{ $p['id'] }}" :disabled="rows.some((r, j) => j !== i && String(r.project_id) === '{{ $p['id'] }}')">{{ $p['label'] }}</option>
                                @endforeach
                            </select>
                            <select class="select" :name="`projects[${i}][role]`" x-model="row.role" :aria-label="`Role project baris ${i + 1}`">
                                @foreach ($roleOptions as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <button class="btn btn-ghost btn-sm btn-icon" type="button" :aria-label="`Hapus baris ${i + 1}`" @click="rows.splice(i, 1)"><x-icon name="x" /></button>
                        </div>
                    </template>
                    <p class="t-12 muted" x-show="rows.length === 0">Belum di-assign ke project mana pun.</p>
                </div>
            @endif

            @if ($lockedMemberships->isNotEmpty())
                <p class="t-12 muted">Juga anggota di project yang tidak bisa Anda atur:
                    {{ $lockedMemberships->map(fn ($p) => $p->name.' ('.\App\Support\ProjectRoles::label($p->pivot->project_role).')')->join(', ') }}.</p>
            @endif
        </section>
    </div>

    <div class="modal-foot">
        <a class="btn btn-secondary" href="{{ $backUrl }}">Batal</a>
        <button class="btn btn-primary" type="submit">Simpan User</button>
    </div>
</form>
