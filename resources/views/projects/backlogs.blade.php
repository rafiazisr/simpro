@php
    $baseQuery = request()->only(['q', 'sprint', 'status', 'priority', 'assignee']);
    $viewUrl = fn ($v) => route('projects.backlogs.index', array_merge([$project], $baseQuery, ['view' => $v]));
    $sprintById = $sprints->keyBy('id');
    $orderedGroups = $sprints->sortBy(fn ($s) => [['active' => 0, 'planned' => 1, 'done' => 2][$s->status->value], -$s->start_date->timestamp])
        ->pluck('id')->push('none')
        ->filter(fn ($k) => $grouped->has($k) || (! $filtered && $k !== 'none'))
        ->values();
@endphp
<x-layouts.app :title="'Backlog · '.$project->name" :crumbs="['Project' => route('projects.index'), $project->name => route('projects.show', $project), 'Backlog']">
    <x-project-header :project="$project" tab="backlog" />

    <form class="toolbar" method="GET" action="{{ route('projects.backlogs.index', $project) }}">
        <input type="hidden" name="view" value="{{ $view }}">
        <div class="input-icon" style="width: 240px; max-width: 100%">
            <x-icon name="search" />
            <input class="input" style="height: 32px" type="search" name="q" value="{{ request('q') }}" placeholder="Cari kode / judul…" aria-label="Cari backlog">
        </div>
        <select class="select" name="sprint" style="width: 170px; height: 32px" aria-label="Filter sprint" onchange="this.form.submit()">
            <option value="">Semua sprint</option>
            @foreach ($sprints as $s)
                <option value="{{ $s->id }}" @selected(request('sprint') == $s->id)>{{ $s->name }} ({{ $s->status->label() }})</option>
            @endforeach
            <option value="none" @selected(request('sprint') === 'none')>Product Backlog</option>
        </select>
        <select class="select" name="status" style="width: 150px; height: 32px" aria-label="Filter status" onchange="this.form.submit()">
            <option value="">Semua status</option>
            @foreach ($statuses as $st)
                <option value="{{ $st->id }}" @selected(request('status') == $st->id)>{{ $st->name }}</option>
            @endforeach
        </select>
        <select class="select" name="priority" style="width: 140px; height: 32px" aria-label="Filter prioritas" onchange="this.form.submit()">
            <option value="">Semua prioritas</option>
            @foreach (App\Enums\Priority::cases() as $p)
                <option value="{{ $p->value }}" @selected(request('priority') === $p->value)>{{ $p->label() }}</option>
            @endforeach
        </select>
        <select class="select" name="assignee" style="width: 160px; height: 32px" aria-label="Filter assignee" onchange="this.form.submit()">
            <option value="">Semua assignee</option>
            @foreach ($assignees as $a)
                <option value="{{ $a->id }}" @selected(request('assignee') == $a->id)>{{ $a->name }}</option>
            @endforeach
        </select>
        @if ($filtered)
            <a class="btn btn-ghost btn-sm" href="{{ route('projects.backlogs.index', [$project, 'view' => $view]) }}">Reset</a>
        @endif
        <span class="grow"></span>
        <div class="seg" role="group" aria-label="Tampilan">
            <a class="{{ $view === 'table' ? 'on' : '' }}" href="{{ $viewUrl('table') }}">Tabel</a>
            <a class="{{ $view === 'board' ? 'on' : '' }}" href="{{ $viewUrl('board') }}">Board</a>
        </div>
        @can('createBacklog', $project)
            <a class="btn btn-primary" href="{{ route('projects.backlogs.create', array_merge([$project], $baseQuery, ['view' => $view])) }}"><x-icon name="plus" />Tambah Backlog</a>
        @endcan
    </form>

    @if ($view === 'board')
        <div class="row t-12 muted" style="gap: 8px">
            <x-icon name="columns" size="sm" />
            @if ($boardSprint)
                Menampilkan {{ $boardSprint->name }} ({{ $boardSprint->rangeLabel() }}). Pilih sprint lain lewat filter.
            @elseif (request('sprint') === 'none')
                Menampilkan Product Backlog.
            @elseif (request('sprint'))
                Menampilkan {{ $sprintById->get(request('sprint'))?->name }}.
            @else
                Belum ada sprint; menampilkan semua backlog.
            @endif
        </div>
        <div class="board" style="display: grid; grid-auto-flow: column; grid-auto-columns: minmax(260px, 1fr); gap: 12px; overflow-x: auto; padding-bottom: 8px">
            @foreach ($statuses as $st)
                @php $cards = $backlogs->where('status_id', $st->id); @endphp
                @if ($cards->isNotEmpty() || ! $st->isCustom())
                    <div class="kcol">
                        <div class="kcol-head"><span class="bar" style="background: {{ $st->color() }}"></span><span class="fw-600">{{ $st->name }}</span><span class="t-12 muted">{{ $cards->count() }}</span></div>
                        @foreach ($cards as $b)
                            <a class="kcard" href="{{ route('backlogs.show', $b) }}" style="text-decoration: none; color: inherit; display: flex; flex-direction: column; gap: 8px">
                                <div class="row between"><span class="code">{{ $b->code }}</span><span class="{{ $b->priority->cssClass() }}"><i></i>{{ $b->priority->label() }}</span></div>
                                <span class="fw-500" style="color: var(--text)">{{ $b->title }}</span>
                                <div class="row between t-12 muted">
                                    <div class="av-group">
                                        @if ($b->programmer)<x-avatar :user="$b->programmer" size="sm" />@endif
                                        @if ($b->qa)<x-avatar :user="$b->qa" size="sm" />@endif
                                    </div>
                                    <span class="{{ $b->isLate() ? 'late' : '' }}">{{ \App\Support\Format::dateRange($b->start_date, $b->end_date, false) }}</span>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endif
            @endforeach
        </div>
    @elseif ($backlogs->isEmpty() && $filtered)
        <div class="card empty" style="min-height: 240px; justify-content: center">
            <h3 class="t-16">Tidak ada backlog yang cocok</h3>
            <p>Ubah kata kunci atau filter.</p>
            <a class="btn btn-secondary" href="{{ route('projects.backlogs.index', $project) }}" style="margin-top: 8px">Reset filter</a>
        </div>
    @elseif ($backlogs->isEmpty() && $sprints->isEmpty())
        <div class="card empty" style="min-height: 240px; justify-content: center">
            <h3 class="t-16">Belum ada backlog</h3>
            @can('createBacklog', $project)
                <a class="btn btn-primary" href="{{ route('projects.backlogs.create', $project) }}" style="margin-top: 8px"><x-icon name="plus" />Tambah Backlog</a>
            @endcan
        </div>
    @else
        <div class="card">
            <div class="table-wrap">
                <table class="table hover table-cards" style="min-width: 1080px">
                    <thead><tr><th>Kode</th><th>Judul</th><th>Status</th><th>Prioritas</th><th>Programmer</th><th>QA</th><th>Mulai – Selesai</th></tr></thead>
                    @foreach ($orderedGroups as $key)
                        @php
                            $items = $grouped->get($key, collect());
                            $sprint = $key === 'none' ? null : $sprintById[$key];
                            $doneCount = $items->filter->isDone()->count();
                        @endphp
                        <tbody x-data="{ open: {{ $key === 'none' || ($sprint && $sprint->status !== App\Enums\SprintStatus::Done) || $filtered ? 'true' : 'false' }} }">
                            <tr class="group">
                                <td colspan="7">
                                    <button type="button" class="row" style="gap: 10px; border: 0; background: none; font: inherit; color: inherit; cursor: pointer; padding: 0" @click="open = !open" :aria-expanded="open">
                                        <x-icon name="chevron-right" x-bind:style="open ? 'transform: rotate(90deg)' : ''" />
                                        @if ($sprint)
                                            {{ $sprint->name }}<span class="{{ $sprint->status->badgeClass() }}"><span class="dot"></span>{{ $sprint->status->label() }}</span>
                                            <span class="t-12 muted fw-500">{{ $sprint->rangeLabel() }} · {{ $items->count() }} backlog · {{ $doneCount }} selesai</span>
                                        @else
                                            Product Backlog<span class="t-12 muted fw-500">Belum masuk sprint · {{ $items->count() }} backlog</span>
                                        @endif
                                    </button>
                                </td>
                            </tr>
                            @forelse ($items as $b)
                                <tr x-show="open" style="cursor: pointer" onclick="window.location='{{ route('backlogs.show', $b) }}'">
                                    <td class="code">{{ $b->code }}</td>
                                    <td class="title"><a href="{{ route('backlogs.show', $b) }}" style="color: inherit; text-decoration: none">{{ $b->title }}</a></td>
                                    <td data-label="Status"><span class="{{ $b->status->badgeClass() }}"><span class="dot"></span>{{ $b->status->name }}</span></td>
                                    <td data-label="Prioritas"><span class="{{ $b->priority->cssClass() }}"><i></i>{{ $b->priority->label() }}</span></td>
                                    <td data-label="Programmer">@if ($b->programmer)<span class="person"><x-avatar :user="$b->programmer" size="sm" />{{ $b->programmer->name }}</span>@else<span class="muted">Belum di-assign</span>@endif</td>
                                    <td data-label="QA">@if ($b->qa)<span class="person"><x-avatar :user="$b->qa" size="sm" />{{ $b->qa->name }}</span>@else<span class="muted">Belum di-assign</span>@endif</td>
                                    <td data-label="Tanggal" class="{{ $b->isLate() ? 'late' : '' }}">{{ $b->start_date || $b->end_date ? \App\Support\Format::dateRange($b->start_date, $b->end_date, false) : '—' }}@if ($b->isLate()) (terlambat)@endif</td>
                                </tr>
                            @empty
                                <tr x-show="open"><td colspan="7" class="muted t-12">Belum ada backlog di {{ $sprint ? $sprint->name : 'Product Backlog' }}.</td></tr>
                            @endforelse
                        </tbody>
                    @endforeach
                </table>
            </div>
            <div class="pagination"><span>{{ $backlogs->count() }} backlog ditampilkan{{ $filtered ? ' (filter aktif)' : '' }}</span></div>
        </div>
    @endif

    @if ($drawer === 'form')
        @include('backlogs.partials.form-drawer')
    @endif
</x-layouts.app>
