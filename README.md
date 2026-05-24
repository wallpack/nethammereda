# NethammerEda MVP (Laravel + Vue + Telegram)

MVP сервиса корпоративных обедов + личный трекер холодильника:
- пользователь заказывает еду на неделю;
- админ доводит цикл до `delivered`;
- после доставки позиции попадают в личный холодильник;
- пользователь отмечает: `Съел 1`, `Съел всё`, `Выбросил`.

## Стек
- Laravel 13
- Vue 3 + Vite
- Filament 5
- Sanctum
- SQLite (локально)

## Реализовано
- Меню и заказы:
  - категории/блюда/циклы/заказы/позиции заказа
  - WebApp каталог и корзина
  - Telegram: `/start`, `/menu`, `/my_order`, `/status`
- Холодильник:
  - сущность `fridge_items`
  - автоперенос из заказа в холодильник при `OrderCycle.status = delivered`
  - idempotent-логика (без дублей при повторном `delivered`)
  - API:
    - `GET /api/my-fridge`
    - `GET /api/my-fridge/history`
    - `PATCH /api/my-fridge/items/{id}/eat-one`
    - `PATCH /api/my-fridge/items/{id}/eat-all`
    - `PATCH /api/my-fridge/items/{id}/discard`
  - Telegram: `/fridge`, `/history`, inline-кнопки по позициям
  - WebApp: вкладка `Холодильник` + история
  - Filament: ресурс `Fridge Items`

## Быстрый запуск
1. Установка:
```bash
composer install
npm install
```

2. Настройка:
```bash
copy .env.example .env
php artisan key:generate
```

3. БД и безопасные демо-данные:
```bash
php artisan migrate
php artisan demo:reset
php artisan storage:link
```

4. Сборка фронта:
```bash
npm run build
```

5. Запуск:
```bash
php artisan serve --host=127.0.0.1 --port=8000
php artisan telegram:webhook:clear
php artisan telegram:poll
```

## Демо-доступы
- Admin: `admin@lunch.local` / `password`
- User: `user@lunch.local` / `password`

## Безопасная подготовка demo data

Команда `php artisan db:seed` больше не является destructive reset и не удаляет существующие заказы, холодильник, циклы или меню. Для явной подготовки локального демо используйте:

```bash
php artisan demo:reset
php artisan storage:link
php artisan serve
```

`php artisan demo:reset` предупреждает об удалении demo data и требует подтверждение. В автоматизированном локальном/testing запуске можно передать `--force`. В окружениях, отличных от `local` и `testing`, destructive reset заблокирован, пока явно не задано `DEMO_RESET_ALLOWED=true`.

Настройки текущего demo-cycle:

```env
LUNCH_BUSINESS_TIMEZONE=Asia/Yekaterinburg
LUNCH_ORDER_DEADLINE_TIME=17:00
DEMO_RESET_ALLOWED=false
```

## Telegram env
```env
TELEGRAM_BOT_TOKEN=
TELEGRAM_WEBHOOK_SECRET=
TELEGRAM_WEBAPP_URL=https://your-public-https-url
TELEGRAM_WEBAPP_AUTH_TTL=86400
TELEGRAM_VERIFY_SSL=false
```

- Для webhook задайте `TELEGRAM_WEBHOOK_SECRET`: без него `/api/telegram/webhook` намеренно отвечает `503`.
- При локальной работе через `php artisan telegram:poll` обновления забираются напрямую у Telegram по bot token, webhook secret для этого режима не нужен.

## Важно про белый экран
- Telegram WebApp должен открываться по публичному HTTPS.
- Если туннель умер, поднимите новый и обновите `TELEGRAM_WEBAPP_URL`.
- Для локального tunnel:
```bash
ssh -R 80:127.0.0.1:8000 nokey@localhost.run
```

## Полезные команды
```bash
php artisan route:list
php artisan telegram:webhook:info
php artisan telegram:webhook:clear
php artisan demo:reset
php artisan test
```
