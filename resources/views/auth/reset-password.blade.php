<x-layouts.guest title="Reset password">
<div style="min-height: 100vh; padding: 32px 16px; display: flex; flex-direction: column; align-items: center; gap: 32px">
    <span class="wordmark" style="font-size: 22px">{{ config('app.name') }}</span>

    <form method="POST" action="{{ route('password.store') }}" class="card auth-card" style="width: 100%; max-width: 420px; padding: 32px; display: flex; flex-direction: column; gap: 18px" novalidate
          x-data="{ pw: '' }">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <input type="hidden" name="email" value="{{ old('email', $request->email) }}">

        <div class="col" style="gap: 6px">
            <h1 class="t-20">Buat password baru</h1>
            <p class="muted">Untuk akun {{ old('email', $request->email) }}</p>
        </div>

        <x-field-error name="email" />

        <div class="field">
            <label class="label" for="password">Password baru</label>
            <input id="password" name="password" type="password" class="input @error('password') is-invalid @enderror" x-model="pw" autocomplete="new-password" required autofocus>
            <ul class="t-12" style="margin: 4px 0 0; padding: 0; list-style: none; display: flex; flex-direction: column; gap: 3px">
                <li class="row" style="gap: 6px" :style="pw.length >= 8 ? 'color: var(--success)' : 'color: var(--text-3)'"><x-icon name="check" size="sm" />Minimal 8 karakter</li>
                <li class="row" style="gap: 6px" :style="/[a-z]/.test(pw) && /[A-Z]/.test(pw) ? 'color: var(--success)' : 'color: var(--text-3)'"><x-icon name="check" size="sm" />Huruf besar dan kecil</li>
            </ul>
            @error('password')
                @if ($message !== 'Konfirmasi password tidak sama.')
                    <span class="error-text"><x-icon name="alert-circle" size="sm" />{{ $message }}</span>
                @endif
            @enderror
        </div>

        <div class="field">
            <label class="label" for="password_confirmation">Konfirmasi password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" class="input @if ($errors->first('password') === 'Konfirmasi password tidak sama.') is-invalid @endif" autocomplete="new-password" required>
            @if ($errors->first('password') === 'Konfirmasi password tidak sama.')
                <span class="error-text"><x-icon name="alert-circle" size="sm" />Konfirmasi password tidak sama.</span>
            @endif
        </div>

        <button class="btn btn-primary btn-lg btn-block" type="submit">Simpan password baru</button>
    </form>
</div>
</x-layouts.guest>
