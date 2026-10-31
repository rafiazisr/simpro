@php
    $daysTotal = (int) $project->start_date->diffInDays($project->end_date) + 1;
    $daysIn = (int) min(max($project->start_date->diffInDays(today(), false) + 1, 0), $daysTotal);
    $pctTime = $daysTotal ? round($daysIn / $daysTotal * 100) : 0;
    $notDone = $stats['total'] - $stats['done'];
@endphp
<x-layouts.app :title="$project->name" :crumbs="['Project' => route('projects.index'), $project->name => route('projects.show', $project), 'Ringkasan']">
    <x-project-header :project="$project" tab="ringkasan" />

    <div class="summary">
        <span><b>{{ $stats['total'] }}</b> backlog</span>
        <span><b>{{ $stats['done'] }}</b> selesai</span>
        <span><b>{{ $stats['ready_for_qa'] }}</b> menunggu QA</span>
        <span @class(['late' => $stats['late']])><b>{{ $stats['late'] }}</b> terlambat</span>
    </div>

    <div style="display: flex; gap: 40px; flex-wrap: wrap; align-items: flex-start">
        <div class="col" style="flex: 999 1 620px; min-width: 0">
            <section class="section">
                <div class="section-head"><h2 class="section-title">Backlog per status</h2></div>
                @if ($stats['total'])
                    <div class="row" style="height: 8px; border-radius: 4px; overflow: hidden; gap: 2px; margin-bottom: 14px">
                        @foreach ($byStatus as $row)
                            @if ($row['count'])<span style="flex: {{ $row['count'] }} 1 0; background: {{ $row['status']->color() }}" title="{{ $row['status']->name }}: {{ $row['count'] }}"></span>@endif
                        @endforeach
                    </div>
                @endif
                <div class="status-counts">
                    @foreach ($byStatus as $row)
                        <a href="{{ route('projects.backlogs.index', [$project, 'status' => $row['status']->id]) }}">
                            <span class="sw" style="background: {{ $row['status']->color() }}"></span>{{ $row['status']->name }}<b>{{ $row['count'] }}</b>
                        </a>
                    @endforeach
                </div>
            </section>

            <section class="section">
                <div class="section-head"><h2 class="section-title">Deskripsi</h2></div>
                <div class="prose" style="font-size: 14px">
                    @if ($project->description)
                        {!! $project->description !!}
                    @else
                        <p class="muted">Belum ada deskripsi.</p>
                    @endif
                </div>
            </section>

            <section class="section">
                <div class="section-head"><h2 class="section-title">Timeline</h2><span class="t-12 muted">Hari ke-{{ $daysIn }} dari {{ $daysTotal }}</span></div>
                <div class="col" style="gap: 8px">
                    <div style="position: relative; height: 24px; border-radius: 4px; background: var(--surface-2); overflow: hidden">
                        <span style="position: absolute; left: 0; top: 0; bottom: 0; width: {{ $pctTime }}%; background: var(--primary-soft); @if ($pctTime > 0 && $pctTime < 100) border-right: 2px solid var(--s-reopen) @endif"></span>
                        @if ($pctTime > 0 && $pctTime < 100)
                            <span class="t-12 fw-600" style="position: absolute; top: 4px; color: var(--danger-text); {{ $pctTime > 70 ? 'right: '.(100 - $pctTime).'%; margin-right: 8px' : 'left: '.$pctTime.'%; margin-left: 8px' }}">Hari ini, {{ \App\Support\Format::date(today(), false) }}</span>
                        @endif
                    </div>
                    <div class="row between wrap t-12 muted" style="gap: 8px">
                        <span>{{ \App\Support\Format::date($project->start_date) }}</span>
                        <span>@if ($activeSprint){{ $activeSprint->name }} aktif ({{ $activeSprint->rangeLabel() }})@else Tidak ada sprint aktif @endif</span>
                        <span>{{ \App\Support\Format::date($project->end_date) }}</span>
                    </div>
                </div>
            </section>
        </div>

        <aside class="col" style="flex: 1 1 280px">
            <section class="section">
                <div class="section-head"><h2 class="section-title">Anggota</h2><a class="link t-12" href="{{ route('projects.members.index', $project) }}">Kelola</a></div>
                <div class="col" style="gap: 14px">
                    @foreach ($roleOptions as $key => $label)
                        @php $list = $membersByRole->get($key, collect()); @endphp
                        @if ($list->isNotEmpty())
                            <div class="col" style="gap: 6px">
                                <span class="overline">{{ $label }}</span>
                                @foreach ($list as $m)
                                    <span class="person"><x-avatar :user="$m" size="sm" />{{ $m->name }}
                                        @if (! $m->is_active || $m->trashed())<span class="tag">Nonaktif</span>@endif
                                    </span>
                                @endforeach
                            </div>
                        @endif
                    @endforeach
                </div>
            </section>

            @can('markDone', $project)
                <section class="section col" style="gap: 10px">
                    @if ($notDone > 0)
                        <span class="t-12" style="color: var(--warning-text)">Masih ada {{ $notDone }} backlog yang belum Done.</span>
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
