<x-layouts.app title="Project" :crumbs="['Project']">
    <div class="page-head">
        <div>
            <h1 class="t-24">Project</h1>
            <p class="sub">{{ $total }} project · {{ $activeCount }} aktif</p>
        </div>
        @can('create', App\Models\Project::class)
            <a class="btn btn-primary" href="{{ route('projects.create') }}"><x-icon name="plus" />Buat Project</a>
        @endcan
    </div>

    @php
        $q = fn (array $extra) => route('projects.index', array_merge(request()->only(['q', 'status', 'view']), $extra));
    @endphp
    <form class="toolbar" method="GET" action="{{ route('projects.index') }}">
        <input type="hidden" name="view" value="{{ $view }}">
        @if ($status)<input type="hidden" name="status" value="{{ $status->value }}">@endif
        <div class="input-icon" style="width: 280px; max-width: 100%">
            <x-icon name="search" />
            <input class="input" type="search" name="q" value="{{ request('q') }}" placeholder="Cari kode atau nama project…" aria-label="Cari project">
        </div>
        <div class="seg" role="group" aria-label="Filter status">
            <a class="{{ ! $status ? 'on' : '' }}" href="{{ $q(['status' => null]) }}">Semua</a>
            @foreach (App\Enums\ProjectStatus::cases() as $s)
                <a class="{{ $status === $s ? 'on' : '' }}" href="{{ $q(['status' => $s->value]) }}">{{ $s->label() }}</a>
            @endforeach
        </div>
        <span class="grow"></span>
        <div class="seg" role="group" aria-label="Tampilan">
            <a class="{{ $view === 'cards' ? 'on' : '' }}" href="{{ $q(['view' => 'cards']) }}"><x-icon name="grid" />Kartu</a>
            <a class="{{ $view === 'table' ? 'on' : '' }}" href="{{ $q(['view' => 'table']) }}"><x-icon name="list" />Tabel</a>
        </div>
    </form>

    @if ($projects->isEmpty())
        <div class="card empty" style="min-height: 320px; justify-content: center">
            <svg class="illu" viewBox="0 0 120 96" aria-hidden="true"><rect x="10" y="20" width="100" height="64" rx="8" style="fill: var(--surface-2); stroke: var(--border-strong)"></rect><path d="M10 34h100" style="stroke: var(--border-strong)"></path><rect x="22" y="46" width="44" height="8" rx="3" style="fill: var(--surface-3)"></rect><rect x="22" y="62" width="30" height="8" rx="3" style="fill: var(--surface-3)"></rect></svg>
            @if (request()->hasAny(['q', 'status']))
                <h3 class="t-16">Tidak ada project yang cocok</h3>
                <p>Ubah kata kunci atau filter status.</p>
                <a class="btn btn-secondary" href="{{ route('projects.index', ['view' => $view]) }}" style="margin-top: 8px">Reset filter</a>
            @else
                <h3 class="t-16">Belum ada project</h3>
                <p>Project yang Anda ikuti akan tampil di sini.</p>
                @can('create', App\Models\Project::class)
                    <a class="btn btn-primary" href="{{ route('projects.create') }}" style="margin-top: 8px"><x-icon name="plus" />Buat Project</a>
                @endcan
            @endif
        </div>
    @elseif ($view === 'cards')
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(min(320px, 100%), 1fr)); gap: 16px">
            @foreach ($projects as $p)
                @php $pct = $p->progress(); @endphp
                <a class="card" href="{{ route('projects.show', $p) }}" style="padding: 18px; display: flex; flex-direction: column; gap: 14px; text-decoration: none; color: inherit">
                    <div class="row between">
                        <span class="row" style="gap: 8px"><span style="width: 10px; height: 10px; border-radius: 3px; background: {{ $p->color }}"></span><span class="code">{{ $p->code }}</span></span>
                        <span class="{{ $p->status->badgeClass() }}"><span class="dot"></span>{{ $p->status->label() }}</span>
                    </div>
                    <div class="col" style="gap: 4px">
                        <span class="t-16">{{ $p->name }}</span>
                        <span class="row t-12 muted" style="gap: 6px"><x-icon name="calendar" size="sm" />{{ \App\Support\Format::dateRange($p->start_date, $p->end_date) }}</span>
                    </div>
                    <div class="col" style="gap: 6px">
                        <div class="row between t-12"><span class="muted">{{ $p->done_backlogs_count }}/{{ $p->backlogs_count }} backlog selesai</span><span class="fw-600">{{ $pct }}%</span></div>
                        <div class="progress {{ $pct === 100 ? 'ok' : '' }}"><span style="width: {{ $pct }}%"></span></div>
                    </div>
                    <div class="row between" style="padding-top: 12px; border-top: 1px solid var(--border)">
                        <div class="av-group">
                            @foreach ($p->members->take(4) as $m)<x-avatar :user="$m" />@endforeach
                            @if ($p->members->count() > 4)<span class="av av-more">+{{ $p->members->count() - 4 }}</span>@endif
                        </div>
                        <span class="row t-12 muted" style="gap: 6px"><x-icon name="zap" size="sm" />{{ $p->active_sprints_count ? $p->active_sprints_count.' sprint aktif' : 'Tidak ada sprint aktif' }}</span>
                    </div>
                </a>
            @endforeach
        </div>
        {{ $projects->links() }}
    @else
        <div class="card">
            <div class="table-wrap">
                <table class="table hover table-cards">
                    <thead><tr><th>Kode</th><th>Nama project</th><th>Rentang tanggal</th><th>Status</th><th style="width: 200px">Progress</th><th>Anggota</th><th>Sprint aktif</th></tr></thead>
                    <tbody>
                    @foreach ($projects as $p)
                        @php $pct = $p->progress(); @endphp
                        <tr style="cursor: pointer" onclick="window.location='{{ route('projects.show', $p) }}'">
                            <td class="code">{{ $p->code }}</td>
                            <td class="title"><a href="{{ route('projects.show', $p) }}" style="color: inherit; text-decoration: none">{{ $p->name }}</a></td>
                            <td data-label="Tanggal">{{ \App\Support\Format::dateRange($p->start_date, $p->end_date) }}</td>
                            <td data-label="Status"><span class="{{ $p->status->badgeClass() }}"><span class="dot"></span>{{ $p->status->label() }}</span></td>
                            <td data-label="Progress"><div class="row" style="gap: 10px"><div class="progress {{ $pct === 100 ? 'ok' : '' }}" style="width: 120px"><span style="width: {{ $pct }}%"></span></div><span class="t-12 fw-600">{{ $pct }}%</span></div></td>
                            <td data-label="Anggota"><div class="av-group">@foreach ($p->members->take(4) as $m)<x-avatar :user="$m" size="sm" />@endforeach</div></td>
                            <td data-label="Sprint aktif">{{ $p->active_sprints_count ?: '—' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            {{ $projects->links() }}
        </div>
    @endif
</x-layouts.app>
