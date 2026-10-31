<x-layouts.app :title="'Anggota · '.$project->name" :crumbs="['Project' => route('projects.index'), $project->name => route('projects.show', $project), 'Anggota']">
    <x-project-header :project="$project" tab="anggota" />

    @if ($errors->has('member') || $errors->has('role'))
        <div class="alert alert-danger" role="alert"><x-icon name="alert-circle" style="margin-top: 2px" /><span>{{ $errors->first('member') ?: $errors->first('role') }}</span></div>
    @endif

    @can('manageMembers', $project)
        <form class="card card-body" method="POST" action="{{ route('projects.members.store', $project) }}">
            @csrf
            <div class="row wrap" style="gap: 10px; align-items: flex-end">
                <div class="field" style="flex: 1 1 320px">
                    <label class="label" for="m-user">Tambah anggota</label>
                    <select id="m-user" name="user_id" class="select @error('user_id') is-invalid @enderror">
                        <option value="">Pilih user aktif…</option>
                        @foreach ($candidates as $u)
                            <option value="{{ $u->id }}" @selected(old('user_id') == $u->id)>{{ $u->name }} — {{ $u->email }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field m-full" style="width: 200px">
                    <label class="label" for="m-role">Role project</label>
                    <select id="m-role" name="role" class="select">
                        @foreach ($roleOptions as $key => $label)
                            <option value="{{ $key }}" @selected(old('role', 'programmer') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <button class="btn btn-primary" type="submit"><x-icon name="plus" />Tambahkan</button>
            </div>
            <x-field-error name="user_id" />
        </form>
    @endcan

    <div class="card">
        <div class="table-wrap">
            <table class="table table-cards">
                <thead><tr><th>Anggota</th><th style="width: 220px">Role project</th><th class="num">Backlog aktif</th><th>Ditambahkan</th><th style="width: 60px"><span class="sr-only">Aksi</span></th></tr></thead>
                <tbody>
                @foreach ($project->members as $m)
                    <tr>
                        <td>
                            <div class="row" style="gap: 12px">
                                <x-avatar :user="$m" />
                                <div class="col" style="line-height: 1.3">
                                    <span class="fw-500" style="color: var(--text)">{{ $m->name }}</span>
                                    <span class="t-12 muted">{{ $m->email }}@if (! $m->is_active || $m->trashed()) · nonaktif @endif</span>
                                </div>
                            </div>
                        </td>
                        <td data-label="Role project">
                            @can('manageMembers', $project)
                                <form method="POST" action="{{ route('projects.members.update', [$project, $m]) }}" style="margin: 0">
                                    @csrf @method('PATCH')
                                    <select name="role" class="select" style="height: 32px" aria-label="Role {{ $m->name }}" onchange="this.form.submit()">
                                        @foreach ($roleOptions as $key => $label)
                                            <option value="{{ $key }}" @selected($m->pivot->project_role === $key)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </form>
                            @else
                                <span class="{{ \App\Support\ProjectRoles::badgeClass($m->pivot->project_role) }}">{{ $roleOptions[$m->pivot->project_role] ?? $m->pivot->project_role }}</span>
                            @endcan
                        </td>
                        <td class="num" data-label="Backlog aktif">{{ $activeCounts[$m->id] ?? 0 }}</td>
                        <td class="muted" data-label="Ditambahkan">{{ \App\Support\Format::date($m->pivot->created_at) }}</td>
                        <td class="actions">
                            @can('manageMembers', $project)
                                <button class="btn btn-ghost btn-sm btn-icon" type="button" aria-label="Keluarkan {{ $m->name }} dari project"
                                        @click="$dispatch('confirm', {
                                            title: @js('Keluarkan '.$m->name.'?'),
                                            message: @js($m->name.' tidak bisa lagi melihat project ini.'),
                                            action: @js(route('projects.members.destroy', [$project, $m])),
                                            method: 'DELETE',
                                            confirmLabel: 'Ya, keluarkan'
                                        })"><x-icon name="user-minus" /></button>
                            @endcan
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="alert alert-info"><x-icon name="info" style="margin-top: 2px" /><span class="t-12">Anggota yang masih punya backlog aktif tidak bisa dikeluarkan atau diganti role-nya sebelum backlog-nya di-assign ulang. Pilihan Programmer/QA di form backlog hanya menampilkan anggota dengan role tersebut.</span></div>
</x-layouts.app>
