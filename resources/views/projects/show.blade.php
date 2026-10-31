@php
    $daysTotal = (int) $project->start_date->diffInDays($project->end_date) + 1;
    $daysIn = (int) min(max($project->start_date->diffInDays(today(), false) + 1, 0), $daysTotal);
    $pctTime = $daysTotal ? round($daysIn / $daysTotal * 100) : 0;
    $notDone = $stats['total'] - $stats['done'];
@endphp
<x-layouts.app :title="$project->name" :crumbs="['Project' => route('projects.index'), $project->name => route('projects.show', $project), 'Ringkasan']">
    <x-project-header :project="$project" tab="ringkasan" />

    <div style="display: flex; gap: 20px; flex-wrap: wrap; align-items: flex-start">
        <div class="col" style="flex: 999 1 620px; min-width: 0; gap: 20px">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px">
                <div class="card stat"><span class="lbl">Total backlog</span><span class="num">{{ $stats['total'] }}</span></div>
                <div class="card stat"><span class="lbl">Selesai</span><span class="num">{{ $stats['done'] }}</span></div>
                <div class="card stat"><span class="lbl">Menunggu QA</span><span class="num">{{ $stats['ready_for_qa'] }}</span></div>
                <div class="card stat"><span class="lbl">Terlambat</span><span class="num" @if ($stats['late']) style="color: var(--danger-text)" @endif>{{ $stats['late'] }}</span></div>
            </div>

            <section class="card">
                <div class="card-head"><span class="t-16">Backlog per status</span><span class="t-12 muted">Semua sprint</span></div>
                <div class="card-body col" style="gap: 16px">
                    @if ($stats['total'])
                        <div class="row" style="height: 12px; border-radius: 6px; overflow: hidden; gap: 2px">
                            @foreach ($byStatus as $row)
                                @if ($row['count'])<span style="flex: {{ $row['count'] }} 1 0; background: {{ $row['status']->color() }}" title="{{ $row['status']->name }}: {{ $row['count'] }}"></span>@endif
                            @endforeach
                        </div>
                    @endif
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap: 12px">
                        @foreach ($byStatus as $row)
                            <a class="col" href="{{ route('projects.backlogs.index', [$project, 'status' => $row['status']->id]) }}" style="gap: 4px; text-decoration: none; color: inherit">
                                <span class="{{ $row['status']->badgeClass() }}" style="align-self: flex-start"><span class="dot"></span>{{ $row['status']->name }}</span>
                                <span class="t-20">{{ $row['count'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            </section>

            <section class="card">
                <div class="card-head"><span class="t-16">Deskripsi</span></div>
                <div class="card-body prose" style="font-size: 14px">
                    @if ($project->description)
                        {!! $project->description !!}
                    @else
                        <p class="muted">Belum ada deskripsi.</p>
                    @endif
                </div>
            </section>

            <section class="card">
                <div class="card-head"><span class="t-16">Timeline</span><span class="t-12 muted">Hari ke-{{ $daysIn }} dari {{ $daysTotal }}</span></div>
                <div class="card-body col" style="gap: 10px">
                    <div style="position: relative; height: 28px; border-radius: 6px; background: var(--surface-2); overflow: hidden">
                        <span style="position: absolute; left: 0; top: 0; bottom: 0; width: {{ $pctTime }}%; background: var(--primary-soft); @if ($pctTime > 0 && $pctTime < 100) border-right: 2px solid var(--s-reopen) @endif"></span>
                        @if ($pctTime > 0 && $pctTime < 100)
                            <span class="t-12 fw-600" style="position: absolute; top: 5px; color: var(--danger-text); {{ $pctTime > 70 ? 'right: '.(100 - $pctTime).'%; margin-right: 8px' : 'left: '.$pctTime.'%; margin-left: 8px' }}">Hari ini · {{ \App\Support\Format::date(today(), false) }}</span>
                        @endif
                    </div>
                    <div class="row between wrap t-12 muted" style="gap: 8px">
                        <span>{{ \App\Support\Format::date($project->start_date) }}</span>
                        <span>@if ($activeSprint){{ $activeSprint->name }} aktif · {{ $activeSprint->rangeLabel() }}@else Tidak ada sprint aktif @endif</span>
                        <span>{{ \App\Support\Format::date($project->end_date) }}</span>
                    </div>
                </div>
            </section>
        </div>

        <aside class="col" style="flex: 1 1 320px; gap: 20px">
            <section class="card">
                <div class="card-head"><span class="t-16">Anggota per role</span><a class="link t-12" href="{{ route('projects.members.index', $project) }}">Kelola</a></div>
                <div class="card-body col" style="gap: 14px">
                    @forelse ($roleOptions as $key => $label)
                        @php $list = $membersByRole->get($key, collect()); @endphp
                        @if ($list->isNotEmpty())
                            <div class="col" style="gap: 8px">
                                <span class="{{ \App\Support\ProjectRoles::badgeClass($key) }}" style="align-self: flex-start">{{ $label }}@if ($list->count() > 1) · {{ $list->count() }}@endif</span>
                                @foreach ($list as $m)
                                    <span class="person"><x-avatar :user="$m" size="sm" />{{ $m->name }}
                                        @if (! $m->is_active || $m->trashed())<span class="badge badge-neutral" style="height: 18px">Nonaktif</span>@endif
                                    </span>
                                @endforeach
                            </div>
                        @endif
                    @empty
                    @endforelse
                </div>
            </section>

            @can('markDone', $project)
                <section class="card card-body col" style="gap: 12px">
                    <span class="t-16">Tandai Project Selesai</span>
                    @if ($notDone > 0)
                        <div class="alert alert-warn" style="padding: 8px 10px"><x-icon name="alert-triangle" style="margin-top: 2px" /><span class="t-12">Masih ada {{ $notDone }} backlog yang belum Done.</span></div>
                    @endif
                    <button class="btn btn-secondary" type="button"
                            @click="$dispatch('confirm', {
                                title: @js('Tandai '.$project->name.' selesai?'),
                                message: 'Status project menjadi Done dan semua sprint ditandai selesai. Backlog baru tidak bisa ditambahkan.',
                                detail: @js($notDone > 0 ? "Masih ada {$notDone} backlog yang belum Done." : ''),
                                action: @js(route('projects.done', $project)),
                                method: 'PATCH',
                                confirmLabel: 'Ya, tandai selesai'
                            })"><x-icon name="check" />Tandai Project Selesai</button>
                </section>
            @endcan
        </aside>
    </div>
</x-layouts.app>
