@props(['icon', 'value', 'label', 'hint' => null, 'tone' => 'neutral'])
<div {{ $attributes->class(['kpi', 'tone-'.$tone]) }}>
    <span class="kpi-ico"><x-icon :name="$icon" /></span>
    <div class="kpi-body">
        <div class="kpi-num">{{ $value }}</div>
        <div class="kpi-lbl">{{ $label }}</div>
        @if ($hint)
            <div class="kpi-hint">{{ $hint }}</div>
        @endif
    </div>
</div>
