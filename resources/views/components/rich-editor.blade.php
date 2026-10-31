@props(['name', 'value' => null, 'label' => null, 'minHeight' => '120px', 'uploadProject' => null])
@php $id = 'rte-'.$name.'-'.\Illuminate\Support\Str::random(4); @endphp
<div class="field">
    @if ($label)<span class="label" id="{{ $id }}-label">{{ $label }}</span>@endif
    <input type="hidden" id="{{ $id }}" name="{{ $name }}" value="{{ $value }}">
    <div class="editor {{ $uploadProject ? 'allow-uploads' : '' }}">
        <trix-editor input="{{ $id }}" class="editor-area prose" style="font-size: 14px; min-height: {{ $minHeight }}"
                     @if ($label) aria-labelledby="{{ $id }}-label" @endif
                     @if ($uploadProject) data-upload-url="{{ route('docs.upload-image') }}" data-upload-project="{{ $uploadProject }}" @endif></trix-editor>
    </div>
    @if ($uploadProject)
        <span class="help">Seret gambar ke editor atau pakai tombol lampiran. Format jpg, png, atau webp, maksimal 2 MB.</span>
    @endif
    <x-field-error :name="$name" />
</div>
