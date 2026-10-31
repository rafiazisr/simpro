@forelse ($activities as $a)
    <a class="act" href="{{ route('backlogs.show', $a->backlog) }}" style="text-decoration: none; color: inherit">
        @if ($a->user)<x-avatar :user="$a->user" size="sm" />@else<span class="av av-sm">?</span>@endif
        <div class="col" style="gap: 2px">
            <div class="what"><b style="color: var(--text)">{{ $a->user?->name ?? 'Sistem' }}</b> {{ $a->verb() }} <span class="code">{{ $a->backlog->code }}</span>@if ($a->hasChange() && $a->new_value) → {{ $a->new_value }}@endif</div>
            <span class="when">{{ $a->created_at->diffForHumans() }}</span>
        </div>
    </a>
@empty
    <p class="t-12 muted">Belum ada aktivitas.</p>
@endforelse
