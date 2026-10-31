@php
    $isEdit = $project !== null;
    $title = $isEdit ? 'Ubah Project' : 'Buat Project';
    $crumbs = $isEdit
        ? ['Project' => route('projects.index'), $project->name => route('projects.show', $project), 'Ubah']
        : ['Project' => route('projects.index'), 'Buat Project'];
    $memberRows = old('members', $isEdit ? [] : [['user_id' => (string) auth()->id(), 'role' => 'pm']]);
@endphp
<x-layouts.app :title="$title" :crumbs="$crumbs">
    <form method="POST" action="{{ $isEdit ? route('projects.update', $project) : route('projects.store') }}" class="col" style="gap: 20px" novalidate>
        @csrf
        @if ($isEdit) @method('PUT') @endif

        <div class="page-head">
            <div>
                <h1 class="t-24">{{ $title }}</h1>
                <p class="sub">{{ $isEdit ? 'Perubahan tanggal harus tetap mencakup semua sprint.' : 'Project baru akan muncul di Timeline setelah disimpan.' }}</p>
            </div>
            <div class="row">
                <a class="btn btn-secondary" href="{{ $isEdit ? route('projects.show', $project) : route('projects.index') }}">Batal</a>
                <button class="btn btn-primary" type="submit">Simpan Project</button>
            </div>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger" role="alert"><x-icon name="alert-circle" style="margin-top: 2px" /><span>Ada {{ $errors->count() }} isian yang perlu diperbaiki.</span></div>
        @endif

        <div style="display: flex; gap: 20px; flex-wrap: wrap; align-items: flex-start">
            <div class="col" style="flex: 999 1 640px; min-width: 0; gap: 20px">
                <section class="card">
                    <div class="card-head"><span class="t-16">Informasi project</span></div>
                    <div class="card-body" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px">
                        <div class="field">
                            <label class="label" for="p-code">Kode @unless ($isEdit)<span class="req">*</span>@endunless</label>
                            @if ($isEdit)
                                <input id="p-code" class="input is-disabled" value="{{ $project->code }}" disabled>
                                <span class="help">Kode tidak bisa diubah karena dipakai di kode backlog.</span>
                            @else
                                <input id="p-code" name="code" class="input @error('code') is-invalid @enderror" value="{{ old('code') }}" maxlength="10" style="text-transform: uppercase" placeholder="PHR" required>
                                @error('code')<x-field-error name="code" />@else<span class="help">Jadi prefix backlog, mis. PHR-1.</span>@enderror
                            @endif
                        </div>
                        <div class="field" style="grid-column: span 2">
                            <label class="label" for="p-name">Nama project <span class="req">*</span></label>
                            <input id="p-name" name="name" class="input @error('name') is-invalid @enderror" value="{{ old('name', $project?->name) }}" required>
                            <x-field-error name="name" />
                        </div>
                        <div style="grid-column: 1 / -1">
                            <x-rich-editor name="description" label="Deskripsi" :value="old('description', $project?->description)" />
                        </div>
                        <div class="field">
                            <label class="label" for="p-start">Tanggal mulai <span class="req">*</span></label>
                            <input id="p-start" name="start_date" type="date" class="input @error('start_date') is-invalid @enderror" value="{{ old('start_date', $project?->start_date?->format('Y-m-d')) }}" required>
                            <x-field-error name="start_date" />
                        </div>
                        <div class="field">
                            <label class="label" for="p-end">Tanggal selesai <span class="req">*</span></label>
                            <input id="p-end" name="end_date" type="date" class="input @error('end_date') is-invalid @enderror" value="{{ old('end_date', $project?->end_date?->format('Y-m-d')) }}" required>
                            <x-field-error name="end_date" />
                        </div>
                        <div class="field">
                            <label class="label" for="p-st">Status <span class="req">*</span></label>
                            <select id="p-st" name="status" class="select @error('status') is-invalid @enderror">
                                @foreach (App\Enums\ProjectStatus::cases() as $s)
                                    <option value="{{ $s->value }}" @selected(old('status', $project?->status->value ?? 'active') === $s->value)>{{ $s->label() }}</option>
                                @endforeach
                            </select>
                            <x-field-error name="status" />
                        </div>
                    </div>
                </section>

                @unless ($isEdit)
                    <section class="card" x-data="{ rows: @js(array_values($memberRows)) }">
                        <div class="card-head">
                            <div><span class="t-16">Anggota project</span><p class="t-12 muted">Minimal 1 Project Manager.</p></div>
                            <button class="btn btn-secondary btn-sm" type="button" @click="rows.push({ user_id: '', role: 'programmer' })"><x-icon name="plus" />Tambah anggota</button>
                        </div>
                        @error('members')<div style="padding: 12px 16px 0"><x-field-error name="members" /></div>@enderror
                        <div class="table-wrap">
                            <table class="table table-cards">
                                <thead><tr><th>User</th><th style="width: 220px">Role project</th><th style="width: 56px"><span class="sr-only">Aksi</span></th></tr></thead>
                                <tbody>
                                <template x-for="(row, i) in rows" :key="i">
                                    <tr>
                                        <td>
                                            <select class="select" :name="`members[${i}][user_id]`" x-model="row.user_id" style="max-width: 360px" :aria-label="`User baris ${i + 1}`">
                                                <option value="">Pilih user…</option>
                                                @foreach ($users as $u)
                                                    <option value="{{ $u->id }}">{{ $u->name }} — {{ $u->email }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td data-label="Role project">
                                            <select class="select" :name="`members[${i}][role]`" x-model="row.role" :aria-label="`Role baris ${i + 1}`">
                                                @foreach ($roleOptions as $key => $label)
                                                    <option value="{{ $key }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td class="actions"><button class="btn btn-ghost btn-sm btn-icon" type="button" :aria-label="`Hapus baris ${i + 1}`" @click="rows.splice(i, 1)"><x-icon name="x" /></button></td>
                                    </tr>
                                </template>
                                </tbody>
                            </table>
                        </div>
                        @foreach ($errors->get('members.*') as $msgs)
                            <div style="padding: 0 16px 12px"><span class="error-text"><x-icon name="alert-circle" size="sm" />{{ $msgs[0] }}</span></div>
                            @break
                        @endforeach
                    </section>
                @endunless
            </div>

            <aside class="col" style="flex: 1 1 300px; gap: 16px">
                @if ($isEdit)
                    <div class="alert alert-info"><x-icon name="info" style="margin-top: 2px" /><span class="t-12">Anggota project dikelola di tab <a class="link" href="{{ route('projects.members.index', $project) }}">Anggota</a>.</span></div>
                @else
                    <div class="alert alert-info"><x-icon name="info" style="margin-top: 2px" /><span class="t-12">Role project menentukan siapa yang muncul di pilihan Programmer dan QA pada form backlog.</span></div>
                @endif
            </aside>
        </div>
    </form>
</x-layouts.app>
