# PROJECT_BRIEF.md

## Product Vision

NethammerEda is an internal corporate lunch ordering service.
The goal is to replace manual Google Sheets / Excel workflows with a web catalog, admin panel and Telegram integration.

The system should feel simple for employees and efficient for admins:
- users order lunches for the week;
- admins collect and send the supplier order;
- delivered food appears in each user's fridge;
- users can track what they still have available.

## Core Business Flow

1. Supplier provides a weekly menu.
2. Admin imports or updates the menu in the service.
3. Admin opens an order cycle for the week.
4. Users choose dishes before the Friday lunch deadline.
5. Admin closes the order cycle.
6. Admin sends the consolidated supplier order.
7. Supplier delivers the food.
8. Admin marks the cycle as delivered.
9. Ordered dishes become FridgeItem records for users.
10. Users mark food as eaten, partially eaten, discarded, or expired.

## User-Facing UX Requirements

### Catalog

The current catalog is acceptable as a base, but it needs UX polish.

Important requirements:
- improve the “Week / Deadline / Order open” block;
- make the deadline and order status understandable to regular users;
- remove the “Filters” button if real filters are not implemented;
- if filters are implemented, they must be useful:
  - category;
  - price;
  - calories;
  - favorites;
  - availability;
- improve the search bar visually and functionally;
- improve dish cards;
- keep cards clear and not overloaded;
- show dish image, title, weight, nutrition, price and add action;
- if a dish has no image, show a clean fallback placeholder;
- support future image_url/image_path for menu items;
- do not generate real dish images in code unless a separate image generation/import process is designed.

### Header

The header should become a useful navigation area.

Requirements:
- logo on the left;
- clear current cycle/status area;
- visible login action for guests;
- after login, show useful actions instead of a useless notification bell;
- possible actions:
  - Favorites;
  - My Order;
  - Fridge;
  - Profile menu;
- “Login” button should be more visible and easier to click.

### Authentication Modal

The current login modal looks good, but should be polished.

Requirements:
- improve spacing and labels;
- keep password visibility toggle;
- show clear validation errors;
- keep it mobile-friendly;
- avoid making it visually heavier than needed.

### Favorites

Favorites currently behave like local UI state.

Requirements:
- keep local favorites if backend endpoint is not implemented;
- later consider persistent favorites per user;
- favorites should be easy to access after login;
- do not hide important favorite/order/fridge flows too deeply inside profile modal.

### My Order

The current “Your order” block does not fit the page visually.

Preferred direction:
- move My Order to a drawer/sheet/modal;
- show it from a header button with item count badge;
- include:
  - dishes;
  - quantities;
  - total;
  - draft/submitted status;
  - submit action;
  - empty state.

### Fridge

The current “Fridge” block should also be redesigned.

Preferred direction:
- move Fridge to drawer/sheet/modal or a dedicated screen;
- show active food available now;
- show portions remaining;
- show expiration info;
- provide actions:
  - Ate 1;
  - Ate all;
  - Discarded;
- show history separately;
- make Telegram fridge view consistent with web fridge logic.

### Mobile Adaptivity

Mobile support is required.

Requirements:
- catalog should work well on phones;
- cards should become single-column;
- categories should become horizontal chips or a mobile sheet;
- order/fridge should open as drawer/bottom-sheet/fullscreen modal;
- buttons should be touch-friendly;
- dialogs should fit mobile viewport;
- no horizontal overflow.

## Admin Panel Requirements

The current Filament admin panel looks outdated and not informative enough.

### General Admin UX

Requirements:
- redesign admin dashboard visually;
- align admin style with NethammerEda branding and login page;
- use clear Russian labels;
- avoid technical enum names in visible UI;
- no mixed Russian/English labels like “in_fridge / expires_at”;
- replace labels like “Категории Меню” with clearer names like “Категории”;
- make admin profile/logout placement logical;
- avoid strange centered “Administrator / Logout” blocks.

### Dashboard

Dashboard should answer:
- what is the current order cycle;
- is ordering open or closed;
- how much time remains until deadline;
- how many users ordered;
- how many orders are drafts/submitted;
- how many items/portions are ordered;
- whether the supplier order is ready to send;
- whether delivery needs to be marked;
- what is currently in fridges;
- what expires soon;
- what is already expired.

Useful widgets:
- Current order cycle;
- Weekly orders summary;
- Supplier order status;
- Fridge overview;
- Expiring soon;
- Recent activity.

### Admin Resources

Preferred labels:
- Users: “Пользователи”
- MenuCategory: “Категории”
- MenuItem: “Блюда”
- OrderCycle: “Недельные циклы”
- Order: “Заказы”
- FridgeItem: “Холодильник”
- MenuImport: “Импорт меню”

Preferred status labels:
- draft: “Черновик”
- open: “Открыт”
- closed: “Закрыт”
- sent_to_supplier: “Отправлен поставщику”
- delivered: “Доставлен”
- archived: “Архивирован”
- submitted: “Отправлен”
- cancelled: “Отменен”
- in_fridge: “В холодильнике”
- eaten: “Съедено”
- discarded: “Выброшено”
- expired: “Просрочено”

## Menu Import Requirements

A key future feature:
Supplier sends a menu file, admin uploads it to the service, and the service updates the catalog.

Goal:
Move away from manual Google Sheets / Excel workflows.

Requirements:
- support XLSX/CSV menu upload in admin panel;
- validate file type and size;
- preview rows before import if reasonable;
- show import errors clearly;
- create categories if missing;
- update existing dishes safely;
- use external_id/supplier_code if available;
- otherwise match by name + category carefully;
- do not delete old dishes physically if they are referenced by orders;
- optionally deactivate missing dishes only if admin explicitly chooses this;
- do not break old OrderItem or FridgeItem records;
- keep import history:
  - filename;
  - status;
  - rows_total;
  - rows_valid;
  - rows_failed;
  - imported_by;
  - imported_at;
  - error report.

## Telegram Requirements

Telegram should complement the web app, not duplicate everything badly.

Required bot capabilities:
- /start
- /menu
- /order
- /fridge
- /history
- /help

Preferred behavior:
- use Telegram WebApp for complex ordering;
- use bot commands for quick status/fridge views;
- /fridge should show what the user can take now;
- /order should show current order and status;
- bot should support buttons for:
  - Open catalog;
  - My order;
  - Fridge;
  - History.

Security:
- never trust telegram_user_id from client without initData validation;
- keep bot token in .env only;
- use webhook secret if webhook is enabled;
- do not log secrets.

Possible notifications:
- order cycle opened;
- deadline soon;
- order sent to supplier;
- delivery marked as arrived;
- food expiring soon.

## Technical Priorities

Preferred development order:
1. Keep Git clean and commit after each task.
2. Maintain AGENTS.md and this PROJECT_BRIEF.md.
3. Harden backend order/fridge domain.
4. Improve Filament admin UX and labels.
5. Add safe menu import.
6. Improve Telegram bot/WebApp.
7. Redesign user catalog UI.
8. Final QA and polish.

## Quality Rules

- Do not rewrite the project from scratch.
- Do not use React.
- Use Vue 3.
- Use shadcn-vue, not React shadcn.
- Preserve backend API compatibility unless explicitly changing API in a tested step.
- Add tests for backend behavior changes.
- Run php artisan test after backend changes.
- Run npm test and npm run build after frontend changes.
- Keep business logic around OrderCycle -> delivered -> FridgeItem idempotent.
- Do not create duplicate FridgeItem records.
- Do not expose .env secrets.
- Do not commit vendor, node_modules, .env or database.sqlite.
- Prefer small safe iterations over large rewrites.
