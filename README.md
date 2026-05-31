# Nethammereda

Corporate food ordering platform for weekly meal cycles, menu catalog, cart, Telegram login/WebApp, admin operations, supplier export, and personal fridge/history.

## Problem Solved
- Employees need a simple flow to order meals by weekly cycle.
- Admins need cycle management, menu import, supplier export, and order tracking.
- Food stock and personal fridge history need post-delivery tracking.
- Supplier side needs clean CSV/XLSX export for operations.

## Key Features
### Customer
- Menu catalog with categories and images.
- Cart and order flow.
- Computed cycle states: `open`, `upcoming`, `closed`.
- Telegram auth and Telegram WebApp support.
- User profile and order history.
- Personal fridge tracking.

### Admin
- Filament admin panel.
- Weekly cycle management.
- Order management.
- Supplier exports in CSV/XLSX.
- Menu categories and menu items management.
- Menu import workflow.
- Fridge item management.
- User management.

### Backend
- Laravel API for web and Telegram clients.
- Order lifecycle logic.
- Menu import/sync pipeline.
- Supplier export services.
- Timezone-aware business cycle handling.
- Automated tests.

## Tech Stack
- Laravel 13
- PHP 8.3
- Vue 3
- Vite
- Tailwind CSS
- Filament
- MySQL or SQLite (local)
- Telegram Bot/WebApp
- Nginx + PHP-FPM + systemd queue on VPS

## Screenshots
`screenshots/`

Screenshots can be added later.

## Architecture
- Vue customer frontend.
- Laravel API/backend.
- Filament admin panel.
- Queue/service workers for async tasks.
- Supplier export module (CSV/XLSX).
- Telegram Bot + WebApp integration layer.

## Local Setup
```bash
cp .env.example .env
composer install
npm install
php artisan key:generate
php artisan migrate
npm run build
php artisan serve
```

SQLite quick setup:
```bash
touch database/database.sqlite
```

PowerShell alternative:
```powershell
New-Item -ItemType File database/database.sqlite -Force
```

## Testing
```bash
php artisan test
npm test
npm run build
composer audit
```

## Deployment Note
- Production deploy is VPS-based.
- `.env` and secrets are not committed.
- Queue workers run via `systemd`.
- Production credentials are never stored in the repository.

## Security
- No secrets in git history or staged files.
- Runtime secrets are managed through `.env`.
- For CI/CD secrets, use GitHub Actions repository secrets.

## Author
Ivan / noctxbt

Full-stack developer focused on Laravel, Vue, product UX, Telegram integrations, admin systems, and production deployment.
