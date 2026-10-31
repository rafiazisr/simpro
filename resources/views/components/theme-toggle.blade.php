@props(['floating' => false])
{{-- Tombol ganti tema terang/gelap (komponen Alpine "themeToggle" di resources/js/app.js). --}}
<button type="button" x-data="themeToggle"
        {{ $attributes->merge(['class' => 'btn btn-ghost btn-icon theme-toggle'.($floating ? ' theme-toggle-floating' : '')]) }}
        @click="toggle()" :aria-pressed="dark.toString()"
        :aria-label="dark ? 'Ganti ke mode terang' : 'Ganti ke mode gelap'"
        :title="dark ? 'Mode terang' : 'Mode gelap'">
    <x-icon name="moon" size="20" x-show="!dark" />
    <x-icon name="sun" size="20" x-show="dark" x-cloak />
</button>
