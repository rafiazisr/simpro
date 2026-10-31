@php
    $styleColors = ['todo' => '#94A3B8', 'progress' => '#3B82F6', 'readyqa' => '#F59E0B', 'testing' => '#8B5CF6', 'done' => '#22C55E', 'reopen' => '#EF4444', 'custom' => '#CBD5E1'];
    $filterUrl = fn ($s) => route('projects.sprints.index', [$project, 'status' => $s]);
@endphp
<x-layouts.app :title="'Sprint · '.$project->name" :crumbs="['Project' => route('projects.index'), $project->name => route('projects.show', $project), 'Sprint']">
    <x-project-header :project="$project" tab="sprint" />

    <div class="toolbar">
        <div class="seg" role="group" aria-label="Filter status sprint">
            <a class="{{ ! $statusFilter ? 'on' : '' }}" href="{{ $filterUrl(null) }}">Semua</a>
            @foreach (App\Enums\SprintStatus::cases() as $s)
                <a class="{{ $statusFilter === $s ? 'on' : '' }}" href="{{ $filterUrl($s->value) }}">{{ $s->label() }}</a>
            @endforeach
        </div>
        <span class="grow"></span>
        @can('manageSprints', $project)
            <a class="btn btn-primary" href="{{ route('projects.sprints.create', $project) }}"><x-icon name="plus" />Tambah Sprint</a>
        @endcan
    </div>

    @if ($sprints->isEmpty())
        <div class="card empty" style="min-height: 260px; justify-content: center">
            <h3 class="t-16">{{ $statusFilter ? 'Tidak ada sprint dengan status ini' : 'Belum ada sprint' }}</h3>
            @can('manageSprints', $project)
                @unless ($statusFilter)
                    <a class="btn btn-primary" href="{{ route('projects.sprints.create', $project) }}" style="margin-top: 8px"><x-icon name="plus" />Tambah Sprint</a>
                @endunless
            @endcan
        </div>
    @else
        <div class="col" style="gap: 12px">
            @foreach ($sprints as $s)
                @php
                    $pct = $s->total_count ? (int) round($s->done_count / $s->total_count * 100) : 0;
                    $isActive = $s->status === App\Enums\SprintStatus::Active;
                @endphp
                <article class="card" @if ($isActive) style="border-color: var(--s-progress); box-shadow: 0 0 0 1px var(--s-progress)" @endif>
                    <div class="row" style="padding: 16px 18px; gap: 20px; align-items: flex-start; flex-wrap: wrap">
                        <div class="col grow" style="gap: 6px; flex: 1 1 320px">
                            <div class="row" style="gap: 10px"><span class="t-16">{{ $s->name }}</span><span class="{{ $s->status->badgeClass() }}"><span class="dot"></span>{{ $s->status->label() }}</span></div>
                            @if ($s->goal)<p class="muted" style="font-size: 13px"><span class="fw-500" style="color: var(--text-2)">Goal:</span> {{ $s->goal }}</p>@endif
                            <span class="row t-12 muted" style="gap: 6px"><x-icon name="calendar" size="sm" />{{ $s->rangeLabel() }} ({{ $s->weeks() }} minggu)</span>
                        </div>
                        <div class="col m-full" style="gap: 6px; width: 240px">
                            <div class="row between t-12"><span class="muted">{{ $s->done_count }}/{{ $s->total_count }} backlog selesai</span><span class="fw-600">{{ $pct }}%</span></div>
                            <div class="progress {{ $pct === 100 ? 'ok' : '' }}"><span style="width: {{ $pct }}%"></span></div>
                            @if ($s->total_count)
                                <div class="row" style="gap: 3px; margin-top: 2px">
                                    @foreach ($s->mix as $style => $n)
                                        <span style="height: 4px; border-radius: 2px; flex: {{ $n }} 1 0; background: {{ $styleColors[$style] ?? '#CBD5E1' }}"></span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                        <div class="row wrap" style="gap: 6px">
                            @if ($s->status !== App\Enums\SprintStatus::Done)
                                @can('markSprintDone', $project)
                                    <button class="btn btn-secondary btn-sm" type="button"
                                            @click="$dispatch('confirm', {
                                                title: @js('Tandai '.$s->name.' selesai?'),
                                                message: 'Status sprint menjadi Done.',
                                                detail: @js(($left = $s->total_count - $s->done_count) > 0 ? "Masih ada {$left} backlog yang belum Done di sprint ini." : ''),
                                                action: @js(route('projects.sprints.done', [$project, $s])),
                                                method: 'PATCH',
                                                confirmLabel: 'Ya, tandai selesai'
                                            })"><x-icon name="check" size="sm" />Tandai Sprint Selesai</button>
                                @endcan
                            @endif
                            <a class="btn btn-ghost btn-sm" href="{{ route('projects.backlogs.index', [$project, 'sprint' => $s->id]) }}">Lihat backlog</a>
                            @can('manageSprints', $project)
                                <a class="btn btn-ghost btn-sm btn-icon" href="{{ route('projects.sprints.edit', [$project, $s]) }}" aria-label="Ubah {{ $s->name }}"><x-icon name="edit" /></a>
                                <button class="btn btn-ghost btn-sm btn-icon" type="button" aria-label="Hapus {{ $s->name }}"
                                        @click="$dispatch('confirm', {
                                            title: @js('Hapus '.$s->name.'?'),
                                            message: 'Backlog di sprint ini akan dipindah ke Product Backlog.',
                                            action: @js(route('projects.sprints.destroy', [$project, $s])),
                                            method: 'DELETE',
                                            confirmLabel: 'Ya, hapus sprint'
                                        })"><x-icon name="trash" /></button>
                            @endcan
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    @endif

    @if ($modal === 'form')
        @php $back = route('projects.sprints.index', $project); @endphp
        <div class="backdrop is-fixed" onclick="window.location='{{ $back }}'"></div>
        <form class="modal is-fixed" role="dialog" aria-labelledby="fs-title" style="top: 80px; width: 560px" novalidate
              method="POST" action="{{ $editing ? route('projects.sprints.update', [$project, $editing]) : route('projects.sprints.store', $project) }}"
              x-data @keydown.escape.window="window.location = @js($back)">
            @csrf
            @if ($editing) @method('PUT') @endif
            <div class="modal-head">
                <div>
                    <h2 id="fs-title" class="t-20">{{ $editing ? 'Ubah '.$editing->name : 'Tambah Sprint' }}</h2>
                    <p class="t-12 muted" style="margin-top: 2px">{{ $project->name }} · rentang project {{ \App\Support\Format::dateRange($project->start_date, $project->end_date) }}</p>
                </div>
                <a class="btn btn-ghost btn-sm btn-icon" href="{{ $back }}" aria-label="Tutup"><x-icon name="x" /></a>
            </div>
            <div class="modal-body">
                <div class="field">
                    <label class="label" for="s-name">Nama sprint <span class="req">*</span></label>
                    <input id="s-name" name="name" class="input @error('name') is-invalid @enderror" value="{{ old('name', $editing?->name ?? $suggestedName) }}" required autofocus>
                    <x-field-error name="name" />
                </div>
                <div class="field">
                    <label class="label" for="s-goal">Goal</label>
                    <textarea id="s-goal" name="goal" class="textarea @error('goal') is-invalid @enderror" rows="3">{{ old('goal', $editing?->goal) }}</textarea>
                    <x-field-error name="goal" />
                </div>
                <div class="m-stack-sm" style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px">
                    <div class="field">
                        <label class="label" for="s-start">Tanggal mulai <span class="req">*</span></label>
                        <input id="s-start" name="start_date" type="date" class="input @error('start_date') is-invalid @enderror" value="{{ old('start_date', $editing?->start_date?->format('Y-m-d')) }}"
                               min="{{ $project->start_date->format('Y-m-d') }}" max="{{ $project->end_date->format('Y-m-d') }}" required>
                        <x-field-error name="start_date" />
                    </div>
                    <div class="field">
                        <label class="label" for="s-end">Tanggal selesai <span class="req">*</span></label>
                        <input id="s-end" name="end_date" type="date" class="input @error('end_date') is-invalid @enderror" value="{{ old('end_date', $editing?->end_date?->format('Y-m-d')) }}"
                               min="{{ $project->start_date->format('Y-m-d') }}" max="{{ $project->end_date->format('Y-m-d') }}" required>
                        <x-field-error name="end_date" />
                    </div>
                </div>
                <div class="field m-full" style="max-width: 240px">
                    <label class="label" for="s-st">Status <span class="req">*</span></label>
                    <select id="s-st" name="status" class="select">
                        @foreach (App\Enums\SprintStatus::cases() as $st)
                            <option value="{{ $st->value }}" @selected(old('status', $editing?->status->value ?? 'planned') === $st->value)>{{ $st->label() }}</option>
                        @endforeach
                    </select>
                    <x-field-error name="status" />
                </div>
            </div>
            <div class="modal-foot">
                <a class="btn btn-secondary" href="{{ $back }}">Batal</a>
                <button class="btn btn-primary" type="submit">Simpan Sprint</button>
            </div>
        </form>
    @endif
</x-layouts.app>
