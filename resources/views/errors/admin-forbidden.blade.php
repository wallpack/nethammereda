<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Доступ к админ-панели запрещён</title>
    <style>
        :root {
            color-scheme: light;
            --bg: #f1f5f9;
            --card: #ffffff;
            --text: #0f172a;
            --muted: #475569;
            --border: #cbd5e1;
            --action: #1d4ed8;
            --action-hover: #1e40af;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100svh;
            display: grid;
            place-items: center;
            padding: 24px 16px;
            font-family: "Inter", "Segoe UI", sans-serif;
            background:
                radial-gradient(120% 80% at 0 0, #dbeafe 0%, transparent 55%),
                radial-gradient(100% 70% at 100% 100%, #e2e8f0 0%, transparent 50%),
                var(--bg);
            color: var(--text);
        }

        .card {
            width: min(100%, 560px);
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 28px 24px;
            box-shadow: 0 14px 36px rgba(15, 23, 42, 0.1);
        }

        h1 {
            margin: 0;
            font-size: clamp(1.4rem, 3.6vw, 1.8rem);
            line-height: 1.25;
            font-weight: 760;
        }

        p {
            margin: 14px 0 0;
            color: var(--muted);
            line-height: 1.55;
            font-size: 0.97rem;
        }

        .actions {
            margin-top: 22px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .link-button,
        .submit-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 40px;
            padding: 0 14px;
            border-radius: 10px;
            font-size: 0.92rem;
            font-weight: 600;
            text-decoration: none;
            border: 1px solid transparent;
            cursor: pointer;
            transition: background-color .15s ease, color .15s ease, border-color .15s ease;
        }

        .link-button {
            color: #0f172a;
            border-color: var(--border);
            background: #ffffff;
        }

        .link-button:hover,
        .link-button:focus-visible {
            background: #f8fafc;
        }

        .submit-button {
            color: #ffffff;
            background: var(--action);
        }

        .submit-button:hover,
        .submit-button:focus-visible {
            background: var(--action-hover);
        }
    </style>
</head>
<body>
    <main class="card" role="main">
        <h1>У вас нет доступа к админ-панели.</h1>
        <p>Войдите под администратором или выйдите из текущего аккаунта.</p>

        <div class="actions">
            <a class="link-button" href="/">На сайт</a>

            <form method="POST" action="{{ route('admin.logout-and-login') }}">
                @csrf
                <button class="submit-button" type="submit">Выйти и войти как администратор</button>
            </form>
        </div>
    </main>
</body>
</html>
