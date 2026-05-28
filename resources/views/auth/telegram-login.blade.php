<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#2563eb">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <title>Nethammereda — вход через Telegram</title>
    <style>
        :root {
            color-scheme: light;
            --bg: #eff3f8;
            --card: #ffffff;
            --text: #0f172a;
            --muted: #6b7280;
            --border: #e2e8f0;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100svh;
            background:
                radial-gradient(130% 90% at 10% 0%, #ddeafe 0%, transparent 52%),
                radial-gradient(120% 80% at 100% 100%, #e8f3ff 0%, transparent 45%),
                var(--bg);
            color: var(--text);
            font-family: "Manrope", "SF Pro Text", "Segoe UI", sans-serif;
        }

        .auth-shell {
            min-height: 100svh;
            display: grid;
            place-items: center;
            padding: 16px 16px 24px;
        }

        .auth-card {
            width: min(100%, 460px);
            border: 1px solid var(--border);
            border-radius: 20px;
            background: var(--card);
            padding: 28px 24px 22px;
            box-shadow: 0 14px 36px rgba(15, 23, 42, 0.10);
            text-align: center;
        }

        .title {
            margin: 0;
            font-size: clamp(1.55rem, 4.2vw, 1.85rem);
            line-height: 1.2;
            font-weight: 800;
            letter-spacing: -0.01em;
            text-wrap: balance;
        }

        .widget-host {
            margin-top: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 54px;
        }

        .widget-host iframe {
            transform: scale(1.04);
            transform-origin: center center;
        }

        .hint {
            margin: 16px 0 0;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.5;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            margin-top: 14px;
            color: #94a3b8;
            font-size: 12px;
            font-weight: 500;
            text-decoration: none;
            transition: color .15s ease;
        }

        .back-link:hover,
        .back-link:focus-visible {
            color: #475569;
        }

        .unavailable {
            margin: 18px 0 0;
            color: #b91c1c;
            font-size: 14px;
            line-height: 1.5;
        }

        @media (max-width: 560px) {
            .auth-card {
                padding: 24px 18px 20px;
            }

            .widget-host iframe {
                transform: scale(1);
            }
        }
    </style>
</head>
<body>
    <main class="auth-shell">
        <section class="auth-card" aria-labelledby="telegram-login-title">
            <h1 class="title" id="telegram-login-title">Вход через Telegram</h1>

            @if ($loginAvailable && $botUsername)
                <div class="widget-host">
                    <script async src="https://telegram.org/js/telegram-widget.js?22"
                        data-telegram-login="{{ $botUsername }}"
                        data-size="large"
                        data-radius="12"
                        data-auth-url="{{ $callbackUrl }}"
                        data-request-access="write"
                        data-userpic="false"></script>
                </div>
            @else
                <p class="unavailable">Вход через Telegram временно недоступен.</p>
            @endif

            <p class="hint">Если окно не появилось, обновите страницу.</p>
            <a class="back-link" href="/">← Вернуться на сайт</a>
        </section>
    </main>
</body>
</html>
