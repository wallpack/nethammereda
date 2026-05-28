<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Вход через Telegram</title>
</head>
<body style="margin:0;background:#f8fafc;color:#0f172a;font-family:system-ui,-apple-system,'Segoe UI',Roboto,sans-serif;">
    <main style="min-height:100vh;display:grid;place-items:center;padding:24px;">
        <section style="width:min(100%,440px);background:#fff;border:1px solid #e2e8f0;border-radius:20px;padding:24px;box-shadow:0 12px 34px rgba(15,23,42,.08);">
            <h1 style="margin:0 0 8px;font-size:26px;line-height:1.15;">Вход через Telegram</h1>
            <p style="margin:0 0 16px;font-size:14px;line-height:1.5;color:#475569;">
                Используйте официальный Telegram Login Widget.
            </p>

            @if ($loginAvailable && $botUsername)
                <div style="display:flex;justify-content:center;min-height:52px;margin-bottom:12px;">
                    <script async src="https://telegram.org/js/telegram-widget.js?22"
                        data-telegram-login="{{ $botUsername }}"
                        data-size="large"
                        data-auth-url="{{ $callbackUrl }}"
                        data-request-access="write"
                        data-userpic="false"></script>
                </div>
            @else
                <p style="margin:0 0 12px;font-size:14px;color:#b91c1c;">
                    Вход через Telegram временно недоступен.
                </p>
            @endif

            <p style="margin:0 0 12px;font-size:13px;line-height:1.5;color:#64748b;">
                Если окно не появилось, обновите страницу.
            </p>
            <p style="margin:0;">
                <a href="/" style="color:#0f62fe;text-decoration:none;font-weight:600;">Вернуться на сайт</a>
            </p>
        </section>
    </main>
</body>
</html>
