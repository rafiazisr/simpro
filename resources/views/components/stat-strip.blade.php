@props(['items'])
{{--
    Bilah statistik ringkas: satu panel dibagi garis tipis.
    Item: label, value, alert (merah bila > 0), hint (opsional), refs (backlog untuk ditautkan), more.
--}}
<div {{ $attributes->class('stat-strip') }}>
    @foreach ($items as $item)
        @php
            $refs = $item['refs'] ?? collect();
            $more = $item['more'] ?? 0;
            $alert = ($item['alert'] ?? false) && $item['value'] > 0;
        @endphp
        <div class="stat-cell">
            <div class="stat-lbl">{{ $item['label'] }}</div>
            <div @class(['stat-val', 'is-alert' => $alert])>{{ $item['value'] }}</div>
            @if ($refs->isNotEmpty() || ! empty($item['hint']))
                <div class="stat-hint">
                    @foreach ($refs as $b)
                        <a class="code" href="{{ route('backlogs.show', $b) }}">{{ $b->code }}</a>@if (! $loop->last), @endif
                    @endforeach
                    @if ($more > 0) <span>+{{ $more }}</span>@endif
                    @if ($refs->isNotEmpty() && ! empty($item['hint'])) <span class="sep">·</span> @endif
                    @if (! empty($item['hint']))<span>{{ $item['hint'] }}</span>@endif
                </div>
            @endif
        </div>
    @endforeach
</div>
