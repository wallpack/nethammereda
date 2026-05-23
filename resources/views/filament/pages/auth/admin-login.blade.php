<div class="nh-admin-login-shell">
    <aside class="nh-admin-login-hero" aria-labelledby="nh-admin-login-hero-title">
        <div class="nh-admin-login-hero__copy">
            <h1 id="nh-admin-login-hero-title">
                Панель управления
                <span>корпоративными обедами</span>
            </h1>
        </div>

        <div class="nh-admin-login-meal" aria-hidden="true">
            <img src="{{ asset('assets/admin/login/food-hero.png') }}" alt="">
        </div>
    </aside>

    <section class="nh-admin-login-card" aria-labelledby="nh-admin-login-title">
        <div class="nh-admin-login-card__body">
            <x-brand.logo
                class="nh-admin-login-card__brand"
                icon-class="nh-admin-login-card__brand-icon"
                word-class="nh-admin-login-card__brand-word"
                data-testid="admin-login-logo"
            />

            <header class="nh-admin-login-card__header">
                <h2 id="nh-admin-login-title">Вход в панель управления</h2>
                <p>Введите рабочие данные для доступа к панели управления.</p>
            </header>

            {{ $this->content }}
        </div>
    </section>

    <x-filament-actions::modals />

    <script>
        (() => {
            if (window.__nhAdminPasswordToggleBound) {
                return;
            }

            window.__nhAdminPasswordToggleBound = true;

            const selector = '.nh-admin-login-card .fi-input-wrp-suffix button[aria-label="Показать пароль"], .nh-admin-login-card .fi-input-wrp-suffix button[aria-label="Скрыть пароль"]';

            const syncPasswordToggle = (button, reveal) => {
                const wrapper = button.closest('.fi-input-wrp');
                const input = wrapper?.querySelector('input.fi-revealable');

                if (!wrapper || !input) {
                    return;
                }

                input.type = reveal ? 'text' : 'password';

                const alpineState = window.Alpine?.$data?.(wrapper);
                if (alpineState && Object.prototype.hasOwnProperty.call(alpineState, 'isPasswordRevealed')) {
                    alpineState.isPasswordRevealed = reveal;
                }

                wrapper.querySelectorAll('.fi-input-wrp-suffix button').forEach((item) => {
                    const showsPassword = item.getAttribute('aria-label') === 'Показать пароль';
                    item.style.display = showsPassword === reveal ? 'none' : 'flex';
                    item.setAttribute('aria-pressed', reveal ? 'true' : 'false');
                });
            };

            document.addEventListener('click', (event) => {
                const button = event.target.closest(selector);

                if (!button) {
                    return;
                }

                syncPasswordToggle(button, button.getAttribute('aria-label') === 'Показать пароль');
            }, true);
        })();
    </script>
</div>
