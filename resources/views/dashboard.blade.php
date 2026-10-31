@php
    $cfg = [
        'dataUrl' => route('dashboard.data'),
        'initial' => $initial,
    ];
@endphp
<x-layouts.app title="Dashboard" :crumbs="['Dashboard']">
    <div class="col" style="gap: 20px" x-data="dashboardPage(@js($cfg))">
        <div class="page-head">
            <div>
                <h1 class="t-24">{{ $isAdmin ? 'Dashboard' : 'Backlog Saya' }}</h1>
            </div>
            @if ($projects->isNotEmpty())
                <div class="row m-full">
                    <label class="t-12 muted" for="dash-project">Project</label>
                    <select id="dash-project" class="select" style="width: 260px; flex: 1 1 auto; min-width: 0" x-model.number="projectId" @change="load(true)">
                        @foreach ($projects as $p)
                            <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->code }})</option>
                        @endforeach
                    </select>
                </div>
            @endif
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px">
            @foreach ($stats as $s)
                <div class="card stat">
                    <span class="lbl">{{ $s['label'] }}</span>
                    <span class="num" @if (! empty($s['danger'])) style="color: var(--danger-text)" @endif>{{ $s['value'] }}</span>
                </div>
            @endforeach
        </div>

        @if (! $project)
            <div class="card empty" style="min-height: 260px; justify-content: center">
                <h3 class="t-16">Belum ada project</h3>
                @unless ($isAdmin)<p>Anda belum menjadi anggota project mana pun.</p>@endunless
                @can('create', App\Models\Project::class)
                    <a class="btn btn-primary" href="{{ route('projects.create') }}" style="margin-top: 8px"><x-icon name="plus" />Buat Project</a>
                @endcan
            </div>
        @else
            <div class="dash-grid">
                {{-- Pie chart status (FR-51 / FR-53) --}}
                <section class="card">
                    <div class="card-head">
                        <div>
                            <span class="t-16">{{ $isAdmin ? 'Status progres pengerjaan' : 'Progres pengerjaan Anda' }}</span>
                            <p class="t-12 muted" x-text="data.project.name + ' · ' + data.total + ' backlog'"></p>
                        </div>
                    </div>
                    <div class="card-body row wrap" style="gap: 24px 32px; align-items: center; justify-content: center; padding: 24px; position: relative">
                        <template x-if="data.total === 0">
                            <p class="muted" style="margin: 0 auto">Belum ada backlog di project ini.</p>
                        </template>
                        <template x-if="data.total > 0">
                            <div class="col" style="align-items: center; gap: 10px; flex: none">
                                <svg class="pie" viewBox="-6 -6 212 212" width="220" height="220" role="img" :aria-label="`Pie chart distribusi status ${data.total} backlog`">
                                    <g x-html="arcsSvg" @click="$event.target.dataset.id && pick(Number($event.target.dataset.id))"></g>
                                </svg>
                                <div class="col" style="align-items: center; text-align: center; gap: 0">
                                    <span style="font-size: 24px; line-height: 30px; font-weight: 700; letter-spacing: -0.02em" x-text="data.selected ? selectedCount + ' backlog' : data.done_pct + '%'"></span>
                                    <span class="t-12 muted" x-text="data.selected ? data.selected.name : 'selesai (Done)'"></span>
                                </div>
                            </div>
                        </template>
                        <div class="legend" x-show="data.total > 0">
                            <template x-for="s in data.slices" :key="s.id">
                                <button type="button" class="legend-item" :class="{ sel: data.selected?.id === s.id, dim: data.selected && data.selected.id !== s.id }"
                                        :aria-pressed="data.selected?.id === s.id" @click="pick(s.id)">
                                    <span class="sw" :style="`background: ${s.color}`"></span><span x-text="s.name"></span>
                                    <span class="val" x-text="s.count"></span><span class="pct" x-text="s.pct + '%'"></span>
                                </button>
                            </template>
                        </div>
                        <div x-show="loading" class="gantt-overlay" x-cloak><span class="spinner"></span></div>
                    </div>
                </section>

                <section class="card">
                    <div class="card-head"><span class="t-16">Aktivitas terbaru</span><a class="link t-12" href="{{ route('notifications.index') }}">Notifikasi</a></div>
                    <div class="card-body activity" x-html="data.activities_html"></div>
                </section>
            </div>

            {{-- List backlog (FR-50 / FR-52) --}}
            <section class="card">
                <div class="row between wrap" style="padding: 0 16px; border-bottom: 1px solid var(--border); gap: 12px">
                    <div class="tabs" style="border-bottom: 0; min-width: 0; max-width: 100%" x-show="!data.selected">
                        <button type="button" class="tab" :class="data.tab === 'programmer' && 'active'" @click="setTab('programmer')">Proses Pengerjaan Programmer<span class="count" x-text="data.n_programmer"></span></button>
                        <button type="button" class="tab" :class="data.tab === 'qa' && 'active'" @click="setTab('qa')">Proses Testing QA<span class="count" x-text="data.n_qa"></span></button>
                    </div>
                    <div class="row" style="padding: 10px 0; gap: 10px" x-show="data.selected" x-cloak>
                        <span class="chip">Status: <span x-text="data.selected?.name"></span>
                            <button type="button" aria-label="Hapus filter status" @click="pick(null)"><x-icon name="x" size="sm" /></button></span>
                    </div>
                    <span class="t-12 muted" style="padding: 10px 0" x-text="data.list_info"></span>
                </div>
                <div class="table-wrap">
                    <table class="table hover table-cards" style="min-width: 860px">
                        <thead><tr><th>Kode</th><th>Judul</th><th>{{ $isAdmin ? 'Assignee' : 'Peran Anda' }}</th><th>Status</th><th>Prioritas</th><th>Tanggal selesai</th></tr></thead>
                        <tbody x-html="data.rows_html"></tbody>
                    </table>
                </div>
            </section>
        @endif
    </div>
</x-layouts.app>
