@php
    $initial = collect((array) request('projects', []))->map(fn ($v) => (int) $v)->filter()->values();
    $cfg = [
        'dataUrl' => route('gantt.data'),
        'backlogsUrl' => route('gantt.sprint-backlogs', '__ID__'),
        'updateUrl' => route('gantt.sprints.update', '__ID__'),
        'allProjects' => $projects->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'code' => $p->code, 'color' => $p->color])->values(),
        'initialProjects' => $project ? [$project->id] : $initial,
        'lockedProject' => $project?->id,
    ];
    $title = $project ? 'Timeline · '.$project->name : 'Timeline';
    $crumbs = $project
        ? ['Project' => route('projects.index'), $project->name => route('projects.show', $project), 'Timeline']
        : ['Timeline'];
@endphp
<x-layouts.app :title="$title" :crumbs="$crumbs">
    @if ($project)
        <x-project-header :project="$project" tab="timeline" />
    @endif

    <div class="col" style="gap: 20px" x-data="ganttPage(@js($cfg))">
        @unless ($project)
            <div class="page-head">
                <div>
                    <h1 class="t-24">Timeline</h1>
                    <p class="sub">Swimlane per project, satu bar per sprint.</p>
                </div>
            </div>
        @endunless

        <div class="toolbar">
            {{-- Filter project (multi); di tab Timeline project, project sudah terkunci --}}
            @unless ($project)
            <div style="position: relative" @click.outside="projectMenu = false" @keydown.escape="projectMenu = false">
                <button type="button" class="fake-input" style="min-width: 260px; font: inherit; cursor: pointer" @click="projectMenu = !projectMenu" :aria-expanded="projectMenu" aria-label="Filter project">
                    <x-icon name="filter" style="color: var(--text-3)" />
                    <span x-text="projectLabel" style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap"></span>
                    <x-icon name="chevron-down" class="chev" />
                </button>
                <div class="popover" x-cloak x-show="projectMenu" style="position: absolute; top: 44px; left: 0; min-width: 280px; z-index: 30; max-height: 320px; overflow-y: auto">
                    @forelse ($projects as $p)
                        <label class="menu-item" style="cursor: pointer">
                            <input type="checkbox" :checked="projects.includes({{ $p->id }})" @change="toggleProject({{ $p->id }})">
                            <span style="width: 8px; height: 8px; border-radius: 2px; background: {{ $p->color }}"></span>
                            {{ $p->name }}<span class="meta code">{{ $p->code }}</span>
                        </label>
                    @empty
                        <div class="t-12 muted" style="padding: 8px">Belum ada project.</div>
                    @endforelse
                </div>
            </div>

            @endunless

            <select class="select" style="width: 180px" aria-label="Filter sprint" x-model="sprintStatus">
                <option value="">Semua sprint</option>
                <option value="active">Hanya Active</option>
                <option value="planned_active">Planned + Active</option>
            </select>

            <div class="row" style="gap: 6px">
                <input type="date" class="input" style="width: 150px" x-model.lazy="from" aria-label="Dari tanggal" :max="to || null">
                <span class="muted">–</span>
                <input type="date" class="input" style="width: 150px" x-model.lazy="to" aria-label="Sampai tanggal" :min="from || null">
            </div>

            <div class="seg" role="group" aria-label="Zoom">
                <button type="button" :class="zoom === 'hari' && 'on'" @click="setZoom('hari')">Hari</button>
                <button type="button" :class="zoom === 'minggu' && 'on'" @click="setZoom('minggu')">Minggu</button>
                <button type="button" :class="zoom === 'bulan' && 'on'" @click="setZoom('bulan')">Bulan</button>
            </div>

            <button type="button" class="btn btn-ghost" @click="reset()"><x-icon name="rotate" />Reset filter</button>
        </div>

        <div class="card" style="position: relative; overflow: hidden">
            <div x-ref="chart" class="app-gantt" style="width: 100%; height: calc(100vh - 300px); min-height: 460px"></div>

            <div x-cloak x-show="loading" class="gantt-overlay"><span class="spinner"></span>Memuat data…</div>

            <div x-cloak x-show="!loading && empty" class="gantt-overlay">
                <div class="empty" style="background: var(--surface)">
                    <h3 class="t-16">Tidak ada sprint pada filter ini</h3>
                    <p>Ubah rentang tanggal atau reset filter.</p>
                    <button type="button" class="btn btn-secondary" style="margin-top: 8px" @click="reset()">Reset filter</button>
                </div>
            </div>
        </div>

        <div class="row wrap t-12 muted" style="gap: 16px">
            <span class="row" style="gap: 6px"><span style="width: 14px; height: 8px; border-radius: 2px; background: #94A3B8"></span>Planned</span>
            <span class="row" style="gap: 6px"><span style="width: 14px; height: 8px; border-radius: 2px; background: #3B82F6"></span>Active</span>
            <span class="row" style="gap: 6px"><span style="width: 14px; height: 8px; border-radius: 2px; background: #22C55E"></span>Done</span>
            <span class="row" style="gap: 6px"><span style="width: 2px; height: 12px; background: #EF4444"></span>Hari ini</span>
        </div>

        {{-- Modal backlog sprint (FR-22) --}}
        <template x-if="modal.open">
            <div @keydown.escape.window="modal.open = false">
                <div class="backdrop is-fixed" @click="modal.open = false"></div>
                <div class="modal is-fixed" role="dialog" aria-labelledby="gm-title" style="top: 64px; width: 860px">
                    <div class="modal-head">
                        <div class="col" style="gap: 4px">
                            <div class="row" style="gap: 10px">
                                <h2 id="gm-title" class="t-20" x-text="modal.sprint.name"></h2>
                                <template x-if="modal.sprint.status"><span :class="modal.sprint.status_class"><span class="dot"></span><span x-text="modal.sprint.status"></span></span></template>
                            </div>
                            <p class="t-12 muted"><span x-text="modal.sprint.project"></span> · <span x-text="modal.sprint.range"></span></p>
                        </div>
                        <button type="button" class="btn btn-ghost btn-sm btn-icon" aria-label="Tutup" @click="modal.open = false"><x-icon name="x" /></button>
                    </div>
                    <div class="modal-body">
                        <template x-if="modal.loading"><div class="row muted" style="gap: 8px"><span class="spinner"></span>Memuat backlog…</div></template>
                        <template x-if="!modal.loading">
                            <div class="col" style="gap: 14px">
                                <div class="col" style="gap: 6px">
                                    <div class="row between t-12"><span class="muted"><span x-text="modal.sprint.done"></span>/<span x-text="modal.sprint.total"></span> backlog selesai</span><span class="fw-600" x-text="modal.sprint.progress + '%'"></span></div>
                                    <div class="progress" :class="modal.sprint.progress === 100 && 'ok'"><span :style="`width: ${modal.sprint.progress}%`"></span></div>
                                </div>
                                <template x-if="modal.backlogs.length === 0"><p class="muted">Belum ada backlog di sprint ini.</p></template>
                                <div class="col" style="gap: 2px; max-height: 52vh; overflow-y: auto">
                                    <template x-for="b in modal.backlogs" :key="b.code">
                                        <a :href="b.url" class="gantt-mini-row">
                                            <div class="col" style="gap: 4px; min-width: 0">
                                                <div class="row" style="gap: 8px"><span class="code" x-text="b.code"></span><span :class="b.status_class"><span class="dot"></span><span x-text="b.status"></span></span></div>
                                                <span class="fw-500" style="color: var(--text); overflow: hidden; text-overflow: ellipsis; white-space: nowrap" x-text="b.title"></span>
                                                <div class="row t-12 muted" style="gap: 6px">
                                                    <template x-if="b.programmer"><span class="person"><span :class="'av av-sm ' + b.programmer.color" x-text="b.programmer.initials"></span><span x-text="b.programmer.name"></span></span></template>
                                                    <template x-if="b.qa"><span class="person"><span :class="'av av-sm ' + b.qa.color" x-text="b.qa.initials"></span><span x-text="b.qa.name"></span></span></template>
                                                </div>
                                            </div>
                                            <div class="col" style="gap: 6px">
                                                <div class="gantt-mini-track"><span :style="barStyle(b)"></span></div>
                                                <span class="t-12" :class="b.late ? 'late' : 'muted'" x-text="b.range + (b.late ? ' · terlambat' : '')"></span>
                                            </div>
                                        </a>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                    <div class="modal-foot">
                        <a class="btn btn-secondary" :href="modal.sprint.url" x-show="modal.sprint.url">Buka di tab Backlog</a>
                        <button type="button" class="btn btn-primary" @click="modal.open = false">Tutup</button>
                    </div>
                </div>
            </div>
        </template>

        {{-- Toast dari aksi Gantt --}}
        <div class="toast-stack is-fixed" aria-live="polite" x-cloak x-show="toast">
            <div class="toast" :class="toast?.type === 'error' ? '' : 'success'">
                <span class="t-ico" :style="toast?.type === 'error' ? 'color: var(--danger)' : ''"><x-icon name="info" /></span>
                <div class="grow" x-text="toast?.message"></div>
            </div>
        </div>
    </div>

    @push('scripts')
        @vite('resources/js/gantt.js')
    @endpush
</x-layouts.app>
