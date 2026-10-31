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

Login memakai email dan password Super Admin dari `.env`.

## Test

```bash
php artisan test
```
