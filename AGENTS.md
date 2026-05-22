# AGENTS.md

## Project Stack

- Laravel backend with Sanctum bearer-token API auth.
- Vue 3 frontend bundled by Vite.
- Tailwind CSS v4 and shadcn-vue components built on Reka UI.
- Filament admin panel.
- SQLite for local development and tests.
- Telegram Bot API and Telegram WebApp authentication.

## Business Domain

- `OrderCycle` is the weekly ordering cycle.
- `Order` is one user's order for an `OrderCycle`.
- `OrderItem` is a selected dish inside an `Order`.
- `FridgeItem` is delivered food in a user's fridge.
- When an `OrderCycle` changes to `delivered`, delivered order items are synced into `FridgeItem` rows.
- Fridge sync must be idempotent. Never create duplicate `FridgeItem` rows for the same `order_item_id`.

## Important Backend Files

- `routes/api.php`: public catalog/auth routes and protected user order/fridge API.
- `routes/console.php`: Telegram webhook/polling commands and fridge expiry scheduler.
- `app/Models`: Eloquent domain models.
- `app/Enums`: persisted domain statuses and user roles.
- `app/Services/DeliveryToFridgeService.php`: delivered-cycle to fridge sync.
- `app/Services/FridgeItemService.php`: eat/discard fridge item mutations.
- `app/Services/Telegram`: Telegram Bot API and WebApp auth logic.
- `app/Observers/OrderCycleObserver.php`: triggers fridge sync on delivered cycles.
- `app/Policies`: user/order/fridge authorization rules.
- `app/Http/Controllers/Api`: JSON API controllers.
- `app/Filament`: admin resources, pages, widgets, and panel provider.
- `database/migrations`: schema source of truth.
- `database/seeders`: local demo data.
- `tests`: PHP feature/unit tests.

## Important Frontend Files

- `resources/js/App.vue`: main catalog, auth, order, fridge UI.
- `resources/js/components/AuthModal.vue`: user login modal.
- `resources/js/components/UserProfileModal.vue`: profile actions modal.
- `resources/js/components/ui`: shadcn-vue primitives.
- `resources/js/lib/utils.js`: `cn()` helper for class merging.
- `resources/css/app.css`: Tailwind v4 theme and app layout.
- `resources/css/filament/admin/theme.css`: Filament admin theme.
- `resources/views/app.blade.php`: SPA shell and Telegram WebApp script.

## Rules For Future Agents

- Use Vue 3. Do not introduce React.
- Use shadcn-vue. Do not use React shadcn components or examples.
- Prefer project primitives and existing services before adding new abstractions.
- Use Context7 or official documentation for current Laravel, Vue, Vite, Tailwind, shadcn-vue, Filament, Sanctum, Telegram, and upload/import APIs.
- Keep changes small, safe, and reviewable.
- Add or update tests before changing business behavior.
- Do not print secrets or real `.env` values.
- Do not commit `/vendor`, `/node_modules`, `.env`, `.env.*`, or `database/database.sqlite`.
- Do not delete real user data without explicit permission.
- Do not rewrite business logic, admin resources, or frontend architecture in one large pass.

## Common Commands

```bash
composer install
npm install
php artisan test
npm test
npm run build
php artisan serve
npm run dev
```
