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
        $q = fn (array $extra) => route('projects.index', array_merge(request()->only(['q', 'status']), $extra));
    @endphp
    <form class="toolbar" method="GET" action="{{ route('projects.index') }}">
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
    </form>

    @if ($projects->isEmpty())
        <div class="card empty" style="min-height: 320px; justify-content: center">
            @if (request()->hasAny(['q', 'status']))
                <h3 class="t-16">Tidak ada project yang cocok</h3>
                <p>Ubah kata kunci atau filter status.</p>
                <a class="btn btn-secondary" href="{{ route('projects.index') }}" style="margin-top: 8px">Reset filter</a>
            @else
                <h3 class="t-16">Belum ada project</h3>
                @can('create', App\Models\Project::class)
                    <a class="btn btn-primary" href="{{ route('projects.create') }}" style="margin-top: 8px"><x-icon name="plus" />Buat Project</a>
                @endcan
            @endif
        </div>
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
