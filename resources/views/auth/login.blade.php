<x-layouts.guest title="Masuk">
<div style="min-height: 100vh; padding: 32px 16px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 24px">
    <span class="wordmark" style="font-size: 22px">{{ config('app.name') }}</span>

    <div class="card auth-card" style="width: 100%; max-width: 380px; padding: 28px">
        <form class="col" method="POST" action="{{ route('login.store') }}" style="gap: 18px" novalidate>
            @csrf
            <div class="col" style="gap: 6px">
                <h1 class="t-20">Masuk</h1>
                <p class="muted">Gunakan email kantor Anda.</p>
            </div>

            @if (session('status'))
                <div class="alert alert-info" role="status"><x-icon name="check" style="margin-top: 2px" /><div>{{ session('status') }}</div></div>
            @endif

            @if ($errors->has('account_disabled') || session('account_disabled'))
                <div class="alert alert-warn" role="alert">
                    <x-icon name="lock" style="margin-top: 2px" />
                    <div>
                        <div class="fw-600">Akun Anda dinonaktifkan</div>
                        <div class="t-12">Hubungi Admin atau Super Admin untuk mengaktifkan kembali akun Anda.</div>
                    </div>
                </div>
            @elseif ($errors->has('email') && old('email') && $errors->first('email') === 'Email atau password salah.')
                <div class="alert alert-danger" role="alert">
                    <x-icon name="alert-circle" style="margin-top: 2px" />
                    <div>
                        <div class="fw-600">Email atau password salah</div>
                        <div class="t-12">Periksa kembali lalu coba lagi.@if (session()->has('attempts_left')) Sisa percobaan: {{ session('attempts_left') }}.@endif</div>
                    </div>
                </div>
            @endif

            @php $credError = $errors->first('email') === 'Email atau password salah.'; @endphp

            <div class="field">
                <label class="label" for="email">Email</label>
                <div class="input-icon">
                    <x-icon name="mail" />
                    <input id="email" name="email" type="email" class="input @if ($errors->has('email')) is-invalid @endif" value="{{ old('email') }}" placeholder="nama@perusahaan.co.id" autocomplete="username" required autofocus>
                </div>
                @unless ($credError)
                    <x-field-error name="email" />
                @endunless
            </div>

            <div class="field" x-data="{ show: false }">
                <div class="row between">
                    <label class="label" for="password">Password</label>
                </div>
                <div class="input-icon">
                    <x-icon name="lock" />
                    <input id="password" name="password" :type="show ? 'text' : 'password'" type="password" class="input @if ($credError || $errors->has('password')) is-invalid @endif" placeholder="Minimal 8 karakter" autocomplete="current-password" required>
                    <button class="btn btn-ghost btn-sm btn-icon trail" type="button" :aria-label="show ? 'Sembunyikan password' : 'Tampilkan password'" @click="show = !show">
                        <x-icon name="eye" x-show="!show" />
                        <x-icon name="eye-off" x-show="show" x-cloak />
                    </button>
                </div>
                <x-field-error name="password" />
            </div>

            <label class="check"><input type="checkbox" name="remember" value="1" @checked(old('remember'))>Ingat saya</label>

            <button class="btn btn-primary btn-lg btn-block" type="submit">Masuk</button>
            <p class="t-12 muted" style="text-align: center">Lupa password? Hubungi Admin.</p>
        </form>
    </div>
</div>
</x-layouts.guest>
