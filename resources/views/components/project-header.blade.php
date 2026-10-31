@props(['project', 'tab'])
@php
    $pm = $project->members->firstWhere('pivot.project_role', 'pm');
    $tabCls = fn (string $t) => $tab === $t ? 'tab active' : 'tab';
@endphp
<div class="col" style="gap: 16px">
    <div class="page-head">
        <div class="col" style="gap: 6px">
            <div class="row" style="gap: 10px">
                <span style="width: 12px; height: 12px; border-radius: 3px; background: {{ $project->color }}"></span>
                <span class="code">{{ $project->code }}</span>
                <span class="{{ $project->status->badgeClass() }}"><span class="dot"></span>{{ $project->status->label() }}</span>
            </div>
            <h1 class="t-24">{{ $project->name }}</h1>
            <div class="row wrap t-12 muted" style="gap: 16px">
                <span class="row" style="gap: 6px"><x-icon name="calendar" size="sm" />{{ \App\Support\Format::dateRange($project->start_date, $project->end_date) }}</span>
                @if ($pm)
                    <span class="row" style="gap: 6px"><x-avatar :user="$pm" size="sm" />PM: {{ $pm->name }}</span>
                @endif
                <span>{{ $project->members->count() }} anggota</span>
            </div>
        </div>
        <div class="row">
            <a class="btn btn-secondary" href="{{ route('gantt.index', ['projects' => [$project->id]]) }}"><x-icon name="gantt" />Lihat di Gantt</a>
            @can('update', $project)
                <a class="btn btn-secondary" href="{{ route('projects.edit', $project) }}"><x-icon name="edit" />Ubah</a>
            @endcan
            @can('delete', $project)
                <button class="btn btn-secondary btn-icon" type="button" aria-label="Hapus project"
                        @click="$dispatch('confirm', {
                            title: @js('Hapus project '.$project->name.'?'),
                            message: 'Project beserta sprint dan backlog-nya tidak akan tampil lagi.',
                            action: @js(route('projects.destroy', $project)),
                            method: 'DELETE',
                            confirmLabel: 'Ya, hapus project'
                        })"><x-icon name="trash" /></button>
            @endcan
        </div>
    </div>
    <nav class="tabs" aria-label="Tab project">
        <a class="{{ $tabCls('ringkasan') }}" href="{{ route('projects.show', $project) }}">Ringkasan</a>
        <a class="{{ $tabCls('sprint') }}" href="{{ route('projects.sprints.index', $project) }}">Sprint<span class="count">{{ $project->sprints_count ?? $project->sprints()->count() }}</span></a>
        <a class="{{ $tabCls('backlog') }}" href="{{ route('projects.backlogs.index', $project) }}">Backlog<span class="count">{{ $project->backlogs_count ?? $project->backlogs()->count() }}</span></a>
        <a class="{{ $tabCls('anggota') }}" href="{{ route('projects.members.index', $project) }}">Anggota<span class="count">{{ $project->members->count() }}</span></a>
        <a class="{{ $tabCls('docs') }}" href="{{ route('projects.docs.index', $project) }}">Dokumentasi</a>
    </nav>
</div>
