@props(['name'])
@error($name)
    <span class="error-text"><x-icon name="alert-circle" size="sm" />{{ $message }}</span>
@enderror
