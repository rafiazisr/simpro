<x-layouts.guest title="Lupa password">
<div style="min-height: 100vh; padding: 32px 16px; display: flex; flex-direction: column; align-items: center; gap: 32px">
    <span class="wordmark" style="font-size: 22px">{{ config('app.name') }}</span>

    <div class="card auth-card" style="width: 100%; max-width: 420px; padding: 32px; display: flex; flex-direction: column; gap: 20px">
        @if (session('reset_link_sent'))
            <div class="modal-icon" style="background: var(--success-soft); color: var(--success)"><x-icon name="mail" size="20" /></div>
            <div class="col" style="gap: 6px">
                <h1 class="t-20">Cek email Anda</h1>
                <p class="muted">Jika <b style="color: var(--text)">{{ session('reset_link_sent') }}</b> terdaftar, tautan reset password sudah dikirim. Periksa juga folder spam.</p>
            </div>
        @else
            <div class="modal-icon info"><x-icon name="key" size="20" /></div>
            <div class="col" style="gap: 6px">
                <h1 class="t-20">Lupa password?</h1>
                <p class="muted">Masukkan email akun Anda. Kami kirim tautan untuk membuat password baru (berlaku 60 menit).</p>
            </div>
            <form method="POST" action="{{ route('password.email') }}" class="col" style="gap: 20px" novalidate>
                @csrf
                <div class="field">
                    <label class="label" for="email">Email</label>
                    <input id="email" name="email" type="email" class="input @error('email') is-invalid @enderror" value="{{ old('email') }}" required autofocus>
                    <x-field-error name="email" />
                </div>
                <button class="btn btn-primary btn-lg btn-block" type="submit">Kirim tautan reset</button>
            </form>
        @endif
        <a class="row link" href="{{ route('login') }}" style="justify-content: center; gap: 6px"><x-icon name="arrow-left" />Kembali ke halaman masuk</a>
    </div>
</div>
</x-layouts.guest>
