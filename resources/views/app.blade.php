<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="Nethammereda is a weekly food ordering service with a dish catalog, cart, Telegram login, personal orders, fridge tracking, and admin-managed delivery cycles.">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <title>Nethammereda — корпоративное питание</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body>
    <div id="app"></div>
</body>
</html>
