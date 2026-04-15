# AGENTS.md

## Run Commands

```bash
composer run dev       # Dev server + queue + vite (concurrently)
composer run test     # Clear config, then run phpunit
npm run dev           # Vite dev server
npm run build         # Build production assets
php artisan tinker   # Interactive REPL
```

## Key Dependencies

- Laravel 12, PHP 8.2+
- Livewire, Maatwebsite Excel, Simple QR Code
- Tailwind CSS v4 + Vite

## Architecture

- Routes: `routes/web.php` (HTTP), `routes/console.php` (commands)
- Middleware alias: `role` (EnsureUserRole)
- Models in `app/Models/`
- Tests use SQLite in-memory (`.env` must not affect test runs)

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install && npm run build
```

## Code Style

Use Laravel Pint: `composer pint`