<x-layouts.guest title="Masuk">
<div style="width: 100%; min-height: 100vh; display: flex; flex-wrap: wrap">
    <aside class="login-aside" style="flex: 1 1 520px; background: #0F172A; color: #E2E8F0; padding: 48px 56px; display: flex; flex-direction: column; justify-content: space-between; gap: 40px">
        <div class="row" style="gap: 10px"><div class="logo">{{ \Illuminate\Support\Str::substr(config('app.name'), 0, 1) }}</div><span class="t-16" style="color: #fff">{{ config('app.name') }}</span></div>
        <div class="col login-copy" style="gap: 16px; max-width: 460px">
            <h1 style="font-size: 32px; line-height: 40px; font-weight: 600; color: #fff; letter-spacing: -0.02em">Dari sprint sampai QA, semua tercatat di satu tempat.</h1>
            <p style="color: #94A3B8; font-size: 15px">Pantau backlog, timeline project, dan serah terima Programmer → QA secara real-time.</p>
        </div>
        <span class="t-12 login-foot" style="color: #94A3B8">© {{ date('Y') }} {{ config('app.name') }} · Aplikasi internal</span>
    </aside>

    <main class="login-main" style="flex: 1 1 520px; display: grid; place-items: center; padding: 48px 24px; background: var(--surface)">
        <form class="col" method="POST" action="{{ route('login.store') }}" style="width: 100%; max-width: 380px; gap: 20px" novalidate>
            @csrf
            <div class="col" style="gap: 6px">
                <h2 class="t-24">Masuk ke {{ config('app.name') }}</h2>
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
                    <a class="link t-12" href="{{ route('password.request') }}">Lupa password?</a>
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
            <p class="t-12 muted" style="text-align: center">Akun dibuat oleh Admin.</p>
        </form>
    </main>
</div>
</x-layouts.guest>
