@php
    $cat = $backlog->status->category;
    $badgeFor = function (?string $value) use ($statusOptions) {
        $s = $statusOptions->firstWhere('name', $value);
        return $s ? $s->badgeClass() : 'badge badge-outline';
    };
@endphp
<x-layouts.app :title="$backlog->code.' · '.$backlog->title"
               :crumbs="['Project' => route('projects.index'), $project->name => route('projects.show', $project), 'Backlog' => route('projects.backlogs.index', $project), $backlog->code]">
    <div class="page-head">
        <div class="col" style="gap: 8px; max-width: 760px">
            <div class="row" style="gap: 8px">
                <span class="code" style="font-size: 13px">{{ $backlog->code }}</span>
                <button class="btn btn-ghost btn-sm btn-icon" type="button" aria-label="Salin tautan"
                        x-data="{ copied: false }" @click="navigator.clipboard.writeText(window.location.href); copied = true; setTimeout(() => copied = false, 1500)"
                        :title="copied ? 'Tautan disalin' : 'Salin tautan'"><x-icon name="link" size="sm" /></button>
            </div>
            <h1 class="t-24">{{ $backlog->title }}</h1>
            <div class="row wrap" style="gap: 12px">
                @if ($canEdit)
                    {{-- PM/Admin: ganti status bebas lewat combobox (status baru boleh) --}}
                    <form method="POST" action="{{ route('backlogs.status', $backlog) }}" class="row wrap" style="gap: 8px; margin: 0; max-width: 100%; align-items: flex-start">
                        @csrf @method('PATCH')
                        <div style="flex: 1 1 240px; min-width: 0">
                            <x-status-combobox :statuses="$statusOptions" :value="$backlog->status->name" />
                        </div>
                        <button class="btn btn-secondary btn-sm" type="submit" style="align-self: flex-start; margin-top: 26px">Simpan status</button>
                    </form>
                @else
                    <span class="{{ $backlog->status->badgeClass() }}"><span class="dot"></span>{{ $backlog->status->name }}</span>
                @endif
                <span class="{{ $backlog->priority->cssClass() }}"><i></i>{{ $backlog->priority->label() }}</span>
                <span class="t-12 muted">
                    {{ $backlog->sprint?->name ?? 'Product Backlog' }}@if ($backlog->start_date || $backlog->end_date) · {{ \App\Support\Format::dateRange($backlog->start_date, $backlog->end_date) }}@endif
                </span>
                @if ($backlog->isLate())<span class="badge" style="background: var(--danger-soft); color: var(--danger-text)">Terlambat</span>@endif
            </div>
        </div>

        <div class="row wrap page-actions">
            {{-- Tombol aksi kontekstual sesuai peran di backlog ini --}}
            @if ($canUpdateStatus)
                @if (($isProgrammer || $canEdit) && $cat->isProgrammerStage())
                    <form method="POST" action="{{ route('backlogs.status', $backlog) }}" style="margin: 0">
                        @csrf @method('PATCH')
                        <input type="hidden" name="action" value="send_to_qa">
                        <button class="btn btn-primary btn-lg" type="submit"><x-icon name="send" />Selesai, kirim ke QA</button>
                    </form>
                @endif
                @if (($isQa || $canEdit) && $cat === App\Enums\StatusCategory::ReadyForQa)
                    <form method="POST" action="{{ route('backlogs.status', $backlog) }}" style="margin: 0">
                        @csrf @method('PATCH')
                        <input type="hidden" name="action" value="start_testing">
                        <button class="btn btn-secondary btn-lg" type="submit"><x-icon name="play" />Mulai testing</button>
                    </form>
                @endif
                @if (($isQa || $canEdit) && ($cat->isQaStage() || $cat === App\Enums\StatusCategory::Done))
                    <form method="POST" action="{{ route('backlogs.status', $backlog) }}" style="margin: 0">
                        @csrf @method('PATCH')
                        <input type="hidden" name="action" value="reopen">
                        <button class="btn btn-danger-outline btn-lg" type="submit"><x-icon name="rotate" />Reopen</button>
                    </form>
                @endif
                @if (($isQa || $canEdit) && $cat->isQaStage())
                    <form method="POST" action="{{ route('backlogs.status', $backlog) }}" style="margin: 0">
                        @csrf @method('PATCH')
                        <input type="hidden" name="action" value="pass">
                        <button class="btn btn-success btn-lg" type="submit"><x-icon name="check" />Lulus (Done)</button>
                    </form>
                @endif
            @endif
            @if ($canEdit)
                <a class="btn btn-secondary btn-lg" href="{{ route('backlogs.edit', [$backlog, 'from' => 'detail']) }}"><x-icon name="edit" />Ubah</a>
            @endif
        </div>
    </div>

    <div style="display: flex; gap: 20px; flex-wrap: wrap; align-items: flex-start">
        <div class="col" style="flex: 999 1 600px; min-width: 0; gap: 20px">
            <section class="card">
                <div class="card-head"><span class="t-16">Detail</span></div>
                <div class="card-body prose" style="font-size: 14px">
                    @if ($backlog->detail)
                        {!! $backlog->detail !!}
                    @else
                        <p class="muted">Belum ada detail.</p>
                    @endif
                </div>
            </section>

            <section class="card">
                <div class="card-head">
                    <span class="t-16">Dokumentasi fitur terhubung</span>
                    @can('editDocs', $project)
                        <a class="btn btn-ghost btn-sm" href="{{ route('projects.docs.index', $project) }}"><x-icon name="plus" size="sm" />Hubungkan</a>
                    @endcan
                </div>
                @if ($backlog->docFeatures->isEmpty())
                    <div class="card-body t-12 muted">Belum terhubung ke dokumentasi fitur mana pun. Hubungkan dari halaman Dokumentasi lewat tombol "Hubungkan Backlog".</div>
                @else
                    <div class="col">
                        @foreach ($backlog->docFeatures as $f)
                            <a class="row" href="{{ route('projects.docs.show', [$project, $f]) }}" style="padding: 12px 16px; gap: 12px; text-decoration: none; color: inherit; @unless ($loop->last) border-bottom: 1px solid var(--border); @endunless">
                                <x-icon name="book" style="color: var(--primary)" />
                                <div class="col grow" style="line-height: 1.35">
                                    <span class="fw-500">{{ $f->title }}</span>
                                    <span class="t-12 muted">{{ $f->module->name }} · diubah {{ $f->editor?->name ?? '—' }}, {{ \App\Support\Format::date($f->updated_at) }}</span>
                                </div>
                                <x-icon name="chevron-right" style="color: var(--text-3)" />
                            </a>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="card" x-data="{ only: 'all' }">
                <div class="card-head">
                    <span class="t-16">Riwayat Aktivitas</span>
                    <div class="seg">
                        <button type="button" :class="only === 'all' && 'on'" @click="only = 'all'">Semua</button>
                        <button type="button" :class="only === 'status' && 'on'" @click="only = 'status'">Status</button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="activity">
                        @foreach ($backlog->activities as $a)
                            <div class="act" @if ($a->field !== 'status') x-show="only === 'all'" @endif>
                                @if ($a->user)<x-avatar :user="$a->user" />@else<span class="av">?</span>@endif
                                <div class="col" style="gap: 4px">
                                    <div class="what"><b style="color: var(--text)">{{ $a->user?->name ?? 'Sistem' }}</b> {{ $a->verb() }}</div>
                                    @if ($a->hasChange())
                                        <div class="row wrap" style="gap: 6px">
                                            <span class="{{ $a->field === 'status' ? $badgeFor($a->old_value) : 'badge badge-outline' }}">@if ($a->field === 'status')<span class="dot"></span>@endif{{ $a->old_value ?? '—' }}</span>
                                            <x-icon name="arrow-right" size="sm" style="color: var(--text-3)" />
                                            <span class="{{ $a->field === 'status' ? $badgeFor($a->new_value) : 'badge badge-outline' }}">@if ($a->field === 'status')<span class="dot"></span>@endif{{ $a->new_value ?? '—' }}</span>
                                        </div>
                                    @endif
                                    <span class="when">{{ $a->created_at->isToday() ? 'Hari ini, '.$a->created_at->format('H.i') : \App\Support\Format::date($a->created_at).', '.$a->created_at->format('H.i') }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        </div>

        <aside class="card m-full" style="flex: 1 1 300px; position: sticky; top: 80px">
            <div class="card-head"><span class="t-16">Properti</span></div>
            <dl class="kv card-body" style="margin: 0; grid-template-columns: 100px minmax(0, 1fr); row-gap: 16px">
                <dt>Status</dt><dd><span class="{{ $backlog->status->badgeClass() }}"><span class="dot"></span>{{ $backlog->status->name }}</span>@if ($backlog->status->isCustom()) <span class="t-12 muted">({{ $cat->label() }})</span>@endif</dd>
                <dt>Prioritas</dt><dd><span class="{{ $backlog->priority->cssClass() }}"><i></i>{{ $backlog->priority->label() }}</span></dd>
                <dt>Sprint</dt><dd>@if ($backlog->sprint)<a class="link" href="{{ route('projects.backlogs.index', [$project, 'sprint' => $backlog->sprint_id]) }}">{{ $backlog->sprint->name }}</a>@else<span class="muted">Product Backlog</span>@endif</dd>
                <dt>Programmer</dt><dd>@if ($backlog->programmer)<span class="person"><x-avatar :user="$backlog->programmer" size="sm" />{{ $backlog->programmer->name }}</span>@else<span class="muted">Belum di-assign</span>@endif</dd>
                <dt>QA</dt><dd>@if ($backlog->qa)<span class="person"><x-avatar :user="$backlog->qa" size="sm" />{{ $backlog->qa->name }}</span>@else<span class="muted">Belum di-assign</span>@endif</dd>
                <dt>Mulai</dt><dd>{{ \App\Support\Format::date($backlog->start_date) }}</dd>
                <dt>Selesai</dt><dd>{{ \App\Support\Format::date($backlog->end_date) }}@if ($backlog->end_date?->isToday()) <span class="badge badge-neutral" style="height: 18px; margin-left: 4px">Hari ini</span>@endif</dd>
                <dt>Project</dt><dd><a class="link" href="{{ route('projects.show', $project) }}">{{ $project->name }}</a></dd>
                <dt>Dibuat</dt><dd class="t-12 muted">{{ $backlog->creator?->name ?? '—' }} · {{ \App\Support\Format::date($backlog->created_at) }}</dd>
                @if ($backlog->completed_at)
                    <dt>Selesai QA</dt><dd class="t-12 muted">{{ \App\Support\Format::date($backlog->completed_at) }}</dd>
                @endif
            </dl>
        </aside>
    </div>
</x-layouts.app>
