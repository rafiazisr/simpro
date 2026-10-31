@php
    $user = auth()->user();
    $canEdit = $user->can('editDocs', $project);
    $canDelete = $user->can('deleteDocs', $project);
    $isEditing = $mode === 'edit';
    $crumbs = ['Project' => route('projects.index'), $project->name => route('projects.show', $project), 'Dokumentasi' => route('projects.docs.index', $project)];
    if ($feature) { $crumbs[] = $feature->title; } elseif ($isEditing) { $crumbs[] = 'Fitur baru'; }
@endphp
<x-layouts.app :title="'Dokumentasi · '.$project->name" :crumbs="$crumbs">
    <x-project-header :project="$project" tab="docs" />

    <div style="display: flex; gap: 20px; align-items: flex-start; flex-wrap: wrap">
        <aside class="card m-full" style="flex: 0 0 280px; max-width: 100%; position: sticky; top: 80px">
            <div class="card-head"><span class="t-16">Isi dokumentasi</span></div>
            <div class="card-body tree" style="padding: 8px">
                @forelse ($modules as $m)
                    <div x-data="{ open: true, renaming: false }" class="col" style="gap: 1px">
                        <div class="tree-node module" style="justify-content: space-between">
                            <button type="button" class="row" style="gap: 6px; border: 0; background: none; font: inherit; color: inherit; cursor: pointer; padding: 0; min-width: 0" @click="open = !open" :aria-expanded="open" x-show="!renaming">
                                <x-icon name="chevron-right" size="sm" x-bind:style="open ? 'transform: rotate(90deg)' : ''" />
                                <span style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap">{{ $m->name }}</span>
                            </button>
                            @if ($canEdit)
                                <form x-show="renaming" x-cloak method="POST" action="{{ route('projects.docs.modules.update', [$project, $m]) }}" class="row" style="gap: 4px; flex: 1">
                                    @csrf @method('PATCH')
                                    <input class="input" name="name" value="{{ $m->name }}" style="height: 28px" aria-label="Nama modul" @keydown.escape="renaming = false">
                                    <button class="btn btn-ghost btn-sm btn-icon" type="submit" aria-label="Simpan nama"><x-icon name="check" size="sm" /></button>
                                </form>
                                <div class="row" style="gap: 0" x-show="!renaming" x-data="{ menu: false }" @click.outside="menu = false">
                                    <a class="btn btn-ghost btn-sm btn-icon" href="{{ route('projects.docs.features.create', [$project, $m]) }}" aria-label="Tambah fitur di {{ $m->name }}" title="Tambah fitur"><x-icon name="plus" size="sm" /></a>
                                    <div style="position: relative">
                                        <button class="btn btn-ghost btn-sm btn-icon" type="button" aria-label="Aksi modul {{ $m->name }}" @click="menu = !menu"><x-icon name="chevron-down" size="sm" /></button>
                                        <div class="popover" x-cloak x-show="menu" style="position: absolute; right: 0; top: 30px; width: 180px; z-index: 20">
                                            <button type="button" class="menu-item" style="width: 100%; border: 0; background: none; font: inherit" @click="renaming = true; menu = false"><x-icon name="edit" />Ubah nama</button>
                                            @foreach (['up' => 'Naikkan', 'down' => 'Turunkan'] as $dir => $label)
                                                <form method="POST" action="{{ route('projects.docs.modules.move', [$project, $m]) }}" style="margin: 0">
                                                    @csrf @method('PATCH')
                                                    <input type="hidden" name="dir" value="{{ $dir }}">
                                                    <button type="submit" class="menu-item" style="width: 100%; border: 0; background: none; font: inherit">{{ $label }}</button>
                                                </form>
                                            @endforeach
                                            @if ($canDelete)
                                                <div class="menu-sep"></div>
                                                <button type="button" class="menu-item danger" style="width: 100%; border: 0; background: none; font: inherit"
                                                        @click="menu = false; $dispatch('confirm', {
                                                            title: @js('Hapus modul '.$m->name.'?'),
                                                            message: @js($m->features->count() ? 'Semua fitur di modul ini ('.$m->features->count().') ikut terhapus.' : 'Modul kosong ini akan dihapus.'),
                                                            action: @js(route('projects.docs.modules.destroy', [$project, $m])),
                                                            method: 'DELETE',
                                                            confirmLabel: 'Ya, hapus modul'
                                                        })"><x-icon name="trash" />Hapus modul</button>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                        <div x-show="open" class="col" style="gap: 1px">
                            @forelse ($m->features as $f)
                                <a class="tree-node {{ $feature?->id === $f->id ? 'active' : '' }}" href="{{ route('projects.docs.show', [$project, $f]) }}" style="padding-left: 30px; text-decoration: none">
                                    <span style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap">{{ $f->title }}</span>
                                </a>
                            @empty
                                <span class="t-12 muted" style="padding: 4px 8px 4px 30px">Belum ada fitur</span>
                            @endforelse
                        </div>
                    </div>
                @empty
                    <span class="t-12 muted" style="padding: 8px">Belum ada modul.</span>
                @endforelse

                @if ($canEdit)
                    <form method="POST" action="{{ route('projects.docs.modules.store', $project) }}" class="col" style="gap: 6px; margin-top: 10px; padding-top: 10px; border-top: 1px solid var(--border)" x-data="{ adding: {{ $errors->has('name') ? 'true' : 'false' }} }">
                        @csrf
                        <button type="button" class="btn btn-ghost btn-sm" style="justify-content: flex-start; color: var(--primary-text)" x-show="!adding" @click="adding = true; $nextTick(() => $refs.name.focus())"><x-icon name="plus" />Tambah modul</button>
                        <div class="row" style="gap: 6px" x-show="adding" x-cloak>
                            <input class="input @error('name') is-invalid @enderror" name="name" x-ref="name" placeholder="Nama modul, mis. Autentikasi" style="height: 32px" aria-label="Nama modul baru">
                            <button class="btn btn-primary btn-sm" type="submit">Buat</button>
                        </div>
                        <x-field-error name="name" />
                    </form>
                @endif
            </div>
        </aside>

        <div class="col" style="flex: 999 1 560px; min-width: 0; gap: 20px">
            @if ($isEditing)
                @php
                    $action = $feature ? route('projects.docs.update', [$project, $feature]) : route('projects.docs.features.store', [$project, $module]);
                    $back = $feature ? route('projects.docs.show', [$project, $feature]) : route('projects.docs.index', $project);
                @endphp
                <form method="POST" action="{{ $action }}" class="card" novalidate>
                    @csrf
                    @if ($feature) @method('PUT') @endif
                    <div class="card-head">
                        <span class="t-16">{{ $feature ? 'Ubah fitur' : 'Fitur baru di '.$module->name }}</span>
                        <div class="row">
                            <a class="btn btn-secondary btn-sm" href="{{ $back }}">Batal</a>
                            <button class="btn btn-primary btn-sm" type="submit">Simpan</button>
                        </div>
                    </div>
                    <div class="card-body col" style="gap: 16px">
                        <div class="m-stack" style="display: grid; grid-template-columns: minmax(0, 1fr) 220px; gap: 16px">
                            <div class="field">
                                <label class="label" for="d-title">Judul fitur <span class="req">*</span></label>
                                <input id="d-title" name="title" class="input @error('title') is-invalid @enderror" value="{{ old('title', $feature?->title) }}" required autofocus placeholder="mis. Login SSO (OAuth 2.0)">
                                <x-field-error name="title" />
                            </div>
                            @if ($feature)
                                <div class="field">
                                    <label class="label" for="d-module">Modul</label>
                                    <select id="d-module" name="doc_module_id" class="select">
                                        @foreach ($modules as $m)
                                            <option value="{{ $m->id }}" @selected((int) old('doc_module_id', $feature->doc_module_id) === $m->id)>{{ $m->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                        </div>
                        <x-rich-editor name="content" label="Isi dokumentasi" :value="old('content', $feature?->content)" min-height="360px" :upload-project="$project->code" />
                    </div>
                </form>
            @elseif ($feature)
                <article class="card">
                    <div class="card-head" style="align-items: flex-start">
                        <div class="col" style="gap: 4px">
                            <span class="t-12 muted">{{ $feature->module->name }}</span>
                            <h2 class="t-20">{{ $feature->title }}</h2>
                            <span class="t-12 muted">Terakhir diubah oleh {{ $feature->editor?->name ?? '—' }} pada {{ \App\Support\Format::date($feature->updated_at) }}, {{ $feature->updated_at->format('H.i') }}</span>
                        </div>
                        <div class="row" style="gap: 4px">
                            @if ($canEdit)
                                @foreach (['up' => 'Naikkan urutan', 'down' => 'Turunkan urutan'] as $dir => $label)
                                    <form method="POST" action="{{ route('projects.docs.move', [$project, $feature]) }}" style="margin: 0">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="dir" value="{{ $dir }}">
                                        <button class="btn btn-ghost btn-sm btn-icon" type="submit" aria-label="{{ $label }}" title="{{ $label }}"><x-icon name="chevron-down" size="sm" style="{{ $dir === 'up' ? 'transform: rotate(180deg)' : '' }}" /></button>
                                    </form>
                                @endforeach
                                <a class="btn btn-secondary btn-sm" href="{{ route('projects.docs.edit', [$project, $feature]) }}"><x-icon name="edit" size="sm" />Ubah</a>
                            @endif
                            @if ($canDelete)
                                <button class="btn btn-ghost btn-sm btn-icon" type="button" aria-label="Hapus fitur"
                                        @click="$dispatch('confirm', {
                                            title: @js('Hapus '.$feature->title.'?'),
                                            message: 'Dokumentasi fitur ini dan tautan ke backlog-nya akan dihapus.',
                                            action: @js(route('projects.docs.destroy', [$project, $feature])),
                                            method: 'DELETE',
                                            confirmLabel: 'Ya, hapus'
                                        })"><x-icon name="trash" /></button>
                            @endif
                        </div>
                    </div>
                    <div class="card-body prose" style="font-size: 14px">
                        @if ($feature->content)
                            {!! $feature->content !!}
                        @else
                            <p class="muted">Belum ada isi. @if ($canEdit)<a class="link" href="{{ route('projects.docs.edit', [$project, $feature]) }}">Tulis sekarang</a>.@endif</p>
                        @endif
                    </div>
                </article>

                <section class="card">
                    <div class="card-head">
                        <span class="t-16">Task Terkait</span>
                        @if ($canEdit && $mode !== 'link')
                            <a class="btn btn-secondary btn-sm" href="{{ route('projects.docs.show', [$project, $feature, 'mode' => 'link']) }}"><x-icon name="link" size="sm" />Hubungkan Backlog</a>
                        @endif
                    </div>

                    @if ($mode === 'link')
                        <form method="POST" action="{{ route('projects.docs.backlogs.link', [$project, $feature]) }}" class="card-body col" style="gap: 10px; border-bottom: 1px solid var(--border)"
                              x-data="{ q: '' }">
                            @csrf
                            <div class="input-icon"><x-icon name="search" /><input class="input" x-model="q" placeholder="Cari kode atau judul backlog…" aria-label="Cari backlog"></div>
                            <div class="col" style="gap: 2px; max-height: 260px; overflow-y: auto; border: 1px solid var(--border); border-radius: var(--radius); padding: 4px">
                                @forelse ($linkCandidates as $b)
                                    <label class="menu-item" style="cursor: pointer" x-show="q === '' || @js(\Illuminate\Support\Str::lower($b->code.' '.$b->title)).includes(q.toLowerCase())">
                                        <input type="checkbox" name="backlog_ids[]" value="{{ $b->id }}">
                                        <span class="code">{{ $b->code }}</span>{{ $b->title }}
                                    </label>
                                @empty
                                    <span class="t-12 muted" style="padding: 8px">Semua backlog project ini sudah terhubung.</span>
                                @endforelse
                            </div>
                            <x-field-error name="backlog_ids" />
                            <div class="row" style="justify-content: flex-end">
                                <a class="btn btn-secondary btn-sm" href="{{ route('projects.docs.show', [$project, $feature]) }}">Batal</a>
                                <button class="btn btn-primary btn-sm" type="submit">Hubungkan</button>
                            </div>
                        </form>
                    @endif

                    @if ($feature->backlogs->isEmpty())
                        <div class="card-body t-12 muted">Belum ada backlog yang terhubung.</div>
                    @else
                        <div class="table-wrap">
                            <table class="table hover table-cards">
                                <thead><tr><th>Kode</th><th>Judul</th><th>Status</th><th>Programmer</th><th>QA</th>@if ($canEdit)<th style="width: 48px"><span class="sr-only">Aksi</span></th>@endif</tr></thead>
                                <tbody>
                                @foreach ($feature->backlogs as $b)
                                    <tr>
                                        <td class="code"><a href="{{ route('backlogs.show', $b) }}" style="color: inherit">{{ $b->code }}</a></td>
                                        <td class="title"><a href="{{ route('backlogs.show', $b) }}" style="color: inherit; text-decoration: none">{{ $b->title }}</a></td>
                                        <td data-label="Status"><span class="{{ $b->status->badgeClass() }}"><span class="dot"></span>{{ $b->status->name }}</span></td>
                                        <td data-label="Programmer">@if ($b->programmer)<span class="person"><x-avatar :user="$b->programmer" size="sm" />{{ $b->programmer->name }}</span>@else<span class="muted">—</span>@endif</td>
                                        <td data-label="QA">@if ($b->qa)<span class="person"><x-avatar :user="$b->qa" size="sm" />{{ $b->qa->name }}</span>@else<span class="muted">—</span>@endif</td>
                                        @if ($canEdit)
                                            <td class="actions">
                                                <form method="POST" action="{{ route('projects.docs.backlogs.unlink', [$project, $feature, $b]) }}" style="margin: 0">
                                                    @csrf @method('DELETE')
                                                    <button class="btn btn-ghost btn-sm btn-icon" type="submit" aria-label="Lepas {{ $b->code }}" title="Lepas dari fitur ini"><x-icon name="x" size="sm" /></button>
                                                </form>
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>
            @else
                <div class="card empty" style="min-height: 360px; justify-content: center">
                    <h3 class="t-16">Belum ada dokumentasi</h3>
                    <p>{{ $canEdit ? 'Buat modul di panel kiri, lalu tambahkan fiturnya.' : 'Dokumentasi project ini belum ditulis.' }}</p>
                </div>
            @endif
        </div>
    </div>
</x-layouts.app>
