@php
    $isEdit = $editing !== null;
    $returnTo = request('from') === 'detail' ? 'detail' : 'list';
    $back = $isEdit && $returnTo === 'detail'
        ? route('backlogs.show', $editing)
        : route('projects.backlogs.index', array_merge([$project], request()->only(['q', 'sprint', 'status', 'priority', 'assignee', 'view'])));
    $user = auth()->user();
    $canPriority = $user->canInProject('priority.manage', $project);
    $canTimeline = $user->canInProject('timeline.manage', $project);
    $defaultSprint = $isEdit ? $editing->sprint_id : (request('sprint') && request('sprint') !== 'none' ? request('sprint') : ($sprints->firstWhere('status.value', 'active')?->id));
    $nextCode = $project->code.'-'.(((int) \App\Models\Backlog::withTrashed()->where('project_id', $project->id)->max('number')) + 1);
@endphp
<div class="backdrop is-fixed" onclick="window.location='{{ $back }}'"></div>
<form class="drawer" role="dialog" aria-labelledby="fb-title" novalidate
      style="position: fixed; top: 0; right: 0; bottom: 0; z-index: 50; width: 640px; max-width: 100%; display: flex; flex-direction: column"
      method="POST" action="{{ $isEdit ? route('backlogs.update', $editing) : route('projects.backlogs.store', $project) }}">
    @csrf
    @if ($isEdit) @method('PUT') @endif
    <input type="hidden" name="return_to" value="{{ $returnTo }}">

    <div class="drawer-head">
        <div>
            <h2 id="fb-title" class="t-20">{{ $isEdit ? 'Ubah '.$editing->code : 'Tambah Backlog' }}</h2>
            <p class="t-12 muted">{{ $project->name }} · @if ($isEdit) dibuat {{ \App\Support\Format::date($editing->created_at) }} @else kode otomatis <span class="code">{{ $nextCode }}</span> @endif</p>
        </div>
        <a class="btn btn-ghost btn-icon" href="{{ $back }}" aria-label="Tutup"><x-icon name="x" size="20" /></a>
    </div>

    <div class="drawer-body" style="flex: 1; overflow-y: auto">
        @if ($errors->any())
            <div class="alert alert-danger" role="alert" style="padding: 10px 12px"><x-icon name="alert-circle" style="margin-top: 2px" /><span>Ada {{ $errors->count() }} isian yang perlu diperbaiki.</span></div>
        @endif

        <div class="field">
            <label class="label" for="b-title">Judul <span class="req">*</span></label>
            <input id="b-title" name="title" class="input @error('title') is-invalid @enderror" value="{{ old('title', $editing?->title) }}" required autofocus>
            <x-field-error name="title" />
        </div>

        <x-rich-editor name="detail" label="Detail" :value="old('detail', $editing?->detail)" min-height="110px" />

        <div class="m-stack-sm" style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px">
            <div class="field">
                <label class="label" for="b-sprint">Sprint</label>
                <select id="b-sprint" name="sprint_id" class="select @error('sprint_id') is-invalid @enderror">
                    <option value="">Product Backlog (belum masuk sprint)</option>
                    @foreach ($sprints as $s)
                        <option value="{{ $s->id }}" @selected((string) old('sprint_id', $defaultSprint) === (string) $s->id)>{{ $s->name }} ({{ $s->rangeLabel() }}){{ $s->status === App\Enums\SprintStatus::Done ? ' · Done' : '' }}</option>
                    @endforeach
                </select>
                <x-field-error name="sprint_id" />
            </div>

            <x-status-combobox :statuses="$statuses" :value="old('status_name', $editing?->status->name)" :invalid="$errors->has('status_name')" />

            <div class="field" style="grid-column: 1 / -1">
                <span class="label" id="b-pr-label">Prioritas <span class="req">*</span></span>
                @php $prValue = old('priority', $editing?->priority->value ?? 'medium'); @endphp
                <div class="seg" role="radiogroup" aria-labelledby="b-pr-label" style="width: 100%" x-data="{ v: @js($prValue) }">
                    @foreach (App\Enums\Priority::cases() as $p)
                        <label style="flex: 1; display: flex" :class="v === '{{ $p->value }}' && 'on'" class="{{ $prValue === $p->value ? 'on' : '' }}">
                            <input type="radio" name="priority" value="{{ $p->value }}" x-model="v" class="sr-only" @checked($prValue === $p->value) @disabled(! $canPriority)>
                            <span class="{{ $p->cssClass() }}" style="margin: auto; cursor: {{ $canPriority ? 'pointer' : 'not-allowed' }}"><i></i>{{ $p->label() }}</span>
                        </label>
                    @endforeach
                </div>
                @unless ($canPriority)<span class="help">Anda tidak punya izin mengatur prioritas.</span>@endunless
                <x-field-error name="priority" />
                @unless ($canPriority)<input type="hidden" name="priority" value="{{ $prValue }}">@endunless
            </div>

            <div class="field">
                <label class="label" for="b-prog">Programmer</label>
                <select id="b-prog" name="programmer_id" class="select @error('programmer_id') is-invalid @enderror">
                    <option value="">Pilih programmer…</option>
                    @foreach ($programmers as $u)
                        <option value="{{ $u->id }}" @selected((string) old('programmer_id', $editing?->programmer_id) === (string) $u->id)>{{ $u->name }}</option>
                    @endforeach
                </select>
                @error('programmer_id')<x-field-error name="programmer_id" />@else<span class="help">Hanya anggota ber-role Programmer ({{ $programmers->count() }}).</span>@enderror
            </div>
            <div class="field">
                <label class="label" for="b-qa">QA</label>
                <select id="b-qa" name="qa_id" class="select @error('qa_id') is-invalid @enderror">
                    <option value="">Pilih QA…</option>
                    @foreach ($qas as $u)
                        <option value="{{ $u->id }}" @selected((string) old('qa_id', $editing?->qa_id) === (string) $u->id)>{{ $u->name }}</option>
                    @endforeach
                </select>
                @error('qa_id')<x-field-error name="qa_id" />@else<span class="help">Wajib jika backlog masuk sprint.</span>@enderror
            </div>

            <div class="field">
                <label class="label" for="b-start">Tanggal mulai</label>
                <input id="b-start" name="start_date" type="date" class="input @error('start_date') is-invalid @enderror" value="{{ old('start_date', $editing?->start_date?->format('Y-m-d')) }}" @disabled(! $canTimeline)>
                <x-field-error name="start_date" />
            </div>
            <div class="field">
                <label class="label" for="b-end">Tanggal selesai</label>
                <input id="b-end" name="end_date" type="date" class="input @error('end_date') is-invalid @enderror" value="{{ old('end_date', $editing?->end_date?->format('Y-m-d')) }}" @disabled(! $canTimeline)>
                <x-field-error name="end_date" />
            </div>
            <span class="help" style="grid-column: 1 / -1; margin-top: -8px">
                {{ $canTimeline ? 'Jika masuk sprint, tanggal harus di dalam rentang sprint.' : 'Anda tidak punya izin mengatur timeline.' }}
            </span>
        </div>
    </div>

    <div class="drawer-foot">
        @unless ($isEdit)
            <label class="check" style="margin-right: auto"><input type="checkbox" name="again" value="1" @checked(request('again'))>Buat lagi setelah simpan</label>
        @endunless
        @if ($isEdit)
            @can('delete', $editing)
                <button class="btn btn-ghost" type="button" style="margin-right: auto; color: var(--danger-text)"
                        @click="$dispatch('confirm', {
                            title: @js('Hapus '.$editing->code.'?'),
                            message: @js('Backlog "'.$editing->title.'" akan dihapus.'),
                            action: @js(route('backlogs.destroy', $editing)),
                            method: 'DELETE',
                            confirmLabel: 'Ya, hapus'
                        })"><x-icon name="trash" />Hapus</button>
            @endcan
        @endif
        <a class="btn btn-secondary" href="{{ $back }}">Batal</a>
        <button class="btn btn-primary" type="submit">Simpan Backlog</button>
    </div>
</form>
