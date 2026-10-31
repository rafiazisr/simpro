@props(['statuses', 'value' => null, 'name' => 'status_name', 'invalid' => false])
@php
    $options = $statuses->map(fn ($s) => [
        'name' => $s->name,
        'cls' => $s->badgeClass(),
        'meta' => $s->isCustom() ? '→ '.$s->category->label() : '',
    ])->values();
@endphp
<div class="field" style="position: relative" x-data="statusCombobox(@js($options), @js($value ?? $statuses->first()?->name))"
     @click.outside="open = false" @keydown.escape="open = false">
    <span class="label" id="{{ $name }}-label">Status <span class="req">*</span></span>
    <input type="hidden" name="{{ $name }}" :value="value">

    <button type="button" class="fake-input @if ($invalid) is-invalid @endif" :class="open && 'is-focus'" style="width: 100%; text-align: left; font: inherit; cursor: pointer"
            aria-haspopup="listbox" :aria-expanded="open" aria-labelledby="{{ $name }}-label" @click="toggle()">
        <span :class="selected.cls"><span class="dot"></span><span x-text="selected.name"></span></span>
        <x-icon name="chevron-down" class="chev" />
    </button>

    <div class="popover" x-cloak x-show="open" x-transition.opacity style="position: absolute; top: 66px; left: 0; right: 0; z-index: 30">
        <div class="input-icon" style="margin-bottom: 4px">
            <x-icon name="search" />
            <input class="input" x-ref="search" x-model="query" placeholder="Ketik untuk mencari atau membuat status…" aria-label="Cari status"
                   @input="active = 0" @keydown.down.prevent="move(1)" @keydown.up.prevent="move(-1)" @keydown.enter.prevent="enter()">
        </div>

        <div role="listbox" style="max-height: 240px; overflow-y: auto">
            <template x-for="(o, i) in results" :key="o.name">
                <button type="button" class="menu-item" :class="{ 'is-active': i === active }" role="option" :aria-selected="o.name === value"
                        style="width: 100%; border: 0; background: none; font: inherit" @mouseenter="active = i" @click="pick(o.name)">
                    <span :class="o.cls"><span class="dot"></span><span x-text="highlight(o.name)[0]"></span><mark x-text="highlight(o.name)[1]"></mark><span x-text="highlight(o.name)[2]"></span></span>
                    <span class="meta" x-text="o.meta"></span>
                    <template x-if="o.name === value"><x-icon name="check" style="margin-left: auto; color: var(--primary)" /></template>
                </button>
            </template>
            <template x-if="q !== '' && results.length === 0">
                <div class="t-12 muted" style="padding: 10px 8px; text-align: center">Tidak ada status "<span x-text="q"></span>"</div>
            </template>
        </div>

        <template x-if="showCreate">
            <button type="button" class="menu-create" style="width: calc(100% + 8px); border-left: 0; border-right: 0; border-bottom: 0; font: inherit; cursor: pointer" @click="create()">
                <x-icon name="plus" />Buat status baru: '<b x-text="q"></b>'
            </button>
        </template>
    </div>
    <span class="help">Ketik nama baru untuk membuat status.</span>
</div>
