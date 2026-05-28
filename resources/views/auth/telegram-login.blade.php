<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#2563eb">
    <title>Nethammereda — вход через Telegram</title>
    <style>
        :root {
            color-scheme: light;
            --bg: #f3f7fd;
            --card: #ffffff;
            --text: #0f172a;
            --muted: #64748b;
            --border: #dbe4f0;
            --brand: #2563eb;
            --brand-dark: #1d4ed8;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100svh;
            background:
                radial-gradient(130% 90% at 12% 0%, #e5efff 0%, transparent 50%),
                radial-gradient(120% 80% at 100% 100%, #e6f4ff 0%, transparent 44%),
                var(--bg);
            color: var(--text);
            font-family: "Manrope", "SF Pro Text", "Segoe UI", sans-serif;
        }

        .auth-shell {
            min-height: 100svh;
            display: grid;
            place-items: center;
            padding: 16px;
        }

        .auth-card {
            width: min(calc(100% - 32px), 460px);
            border: 1px solid var(--border);
            border-radius: 22px;
            background: var(--card);
            padding: 28px 26px 24px;
            box-shadow: 0 16px 42px rgba(15, 23, 42, 0.09);
            text-align: center;
        }

        .logo-mark {
            width: 48px;
            height: 48px;
            margin: 0 auto 10px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--brand), var(--brand-dark));
            color: #ffffff;
            font-size: 26px;
            font-weight: 800;
            line-height: 1;
            letter-spacing: -0.01em;
        }

        .brand-label {
            margin: 0 0 10px;
            color: var(--muted);
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.03em;
            text-transform: uppercase;
        }

        .title {
            margin: 0;
            font-size: clamp(1.75rem, 4vw, 1.95rem);
            line-height: 1.16;
            font-weight: 800;
            letter-spacing: -0.01em;
            text-wrap: balance;
        }

        .subtitle {
            margin: 8px 0 0;
            color: var(--muted);
            font-size: 15px;
            line-height: 1.5;
        }

        .widget-shell {
            margin-top: 20px;
            border-radius: 16px;
            border: 1px solid #d9e6ff;
            background: linear-gradient(180deg, #f8fbff 0%, #f4f8ff 100%);
            padding: 16px 12px;
        }

        .widget-frame {
            min-height: 54px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .widget-frame iframe {
            transform: scale(1.04);
            transform-origin: center center;
        }

        .hint {
            margin: 14px 0 0;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.5;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: 14px;
            color: #6b7280;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            transition: color .15s ease;
        }

        .back-link:hover,
        .back-link:focus-visible {
            color: #334155;
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
                border-radius: 20px;
            }

            .widget-shell {
                padding: 14px 8px;
            }

            .widget-frame iframe {
                transform: scale(1);
            }
        }
    </style>
</head>
<body>
    <main class="auth-shell">
        <section class="auth-card" aria-labelledby="telegram-login-title">
            <div class="logo-mark" data-testid="telegram-login-logo-mark" aria-hidden="true">N</div>
            <p class="brand-label">Nethammereda — корпоративное питание</p>

            <h1 class="title" id="telegram-login-title">Вход через Telegram</h1>
            <p class="subtitle">Быстрый вход в Nethammereda</p>

            @if ($loginAvailable && $botUsername)
                <div class="widget-shell">
                    <div class="widget-frame">
                        <script async src="https://telegram.org/js/telegram-widget.js?22"
                            data-telegram-login="{{ $botUsername }}"
                            data-size="large"
                            data-radius="12"
                            data-auth-url="{{ $callbackUrl }}"
                            data-request-access="write"
                            data-userpic="false"></script>
                    </div>
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
