# Simpro

Aplikasi project management internal (Laravel 11, PHP 8.2).

## Instalasi

```bash
git clone https://github.com/rafiazisr/simpro.git
cd simpro
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Buat database `simpro`, sesuaikan `DB_*` dan `SUPERADMIN_*` di `.env`, lalu:

```bash
php artisan storage:link
php artisan migrate --seed
npm run dev
php artisan serve
```

Login memakai email dan password Super Admin dari `.env`. Password user diganti oleh Admin lewat menu Manajemen User; untuk Super Admin atau keadaan darurat:

```bash
php artisan user:reset-password email@kantor.com
```

## Test

```bash
php artisan test
```

## Production

Di `.env` server: `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, dan `SUPERADMIN_PASSWORD` minimal 12 karakter (seeder menolak password bawaan).
