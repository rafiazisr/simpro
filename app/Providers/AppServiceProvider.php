<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Super Admin otomatis punya semua permission (nama permission selalu
        // memakai titik, mis. "user.manage"). Method Policy tetap dievaluasi,
        // sehingga aturan seperti "Super Admin tidak bisa dihapus" tetap berlaku.
        Gate::before(fn (User $user, string $ability) => $user->isSuperAdmin() && str_contains($ability, '.') ? true : null);

        Paginator::defaultView('vendor.pagination.app');

        // Email reset password dalam Bahasa Indonesia.
        ResetPassword::toMailUsing(function (User $user, string $token) {
            $url = route('password.reset', ['token' => $token, 'email' => $user->getEmailForPasswordReset()]);

            return (new MailMessage)
                ->subject('Reset password '.config('app.name'))
                ->greeting("Halo, {$user->name}")
                ->line('Kami menerima permintaan untuk mengganti password akun '.config('app.name').' Anda.')
                ->action('Buat password baru', $url)
                ->line('Tautan ini berlaku selama 60 menit.')
                ->line('Abaikan email ini jika Anda tidak meminta reset password.')
                ->salutation('Salam, Tim '.config('app.name'));
        });
    }
}
