import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';

const appRoot = document.getElementById('app');
let appMounted = false;

const renderAppFallback = () => {
    if (!appRoot) {
        return;
    }

    appRoot.innerHTML = `
        <div style="min-height:100vh;display:grid;place-items:center;background:#f8fafc;color:#0f172a;padding:24px;">
            <div style="max-width:420px;text-align:center;font-family:system-ui,-apple-system,'Segoe UI',Roboto,sans-serif;">
                <p style="font-size:20px;font-weight:600;margin:0 0 10px;">Не удалось загрузить приложение.</p>
                <p style="font-size:14px;line-height:1.5;margin:0 0 16px;">Обновите страницу.</p>
                <button id="app-reload-button" type="button" style="height:44px;padding:0 16px;border:0;border-radius:10px;background:#1d4ed8;color:#fff;font-weight:600;cursor:pointer;">
                    Обновить
                </button>
            </div>
        </div>
    `;

    document.getElementById('app-reload-button')?.addEventListener('click', () => {
        window.location.reload();
    });
};

const logClientError = (type, errorLike) => {
    const message = errorLike instanceof Error
        ? errorLike.message
        : String(errorLike ?? 'unknown_error');

    const stack = errorLike instanceof Error ? errorLike.stack : undefined;

    console.error(type, {
        message,
        stack,
    });
};

window.addEventListener('error', (event) => {
    logClientError('client_runtime_error', event.error ?? event.message);

    if (!appMounted) {
        renderAppFallback();
    }
});

window.addEventListener('unhandledrejection', (event) => {
    logClientError('client_unhandled_rejection', event.reason);

    if (!appMounted) {
        renderAppFallback();
    }
});

try {
    const app = createApp(App);

    app.config.errorHandler = (error) => {
        logClientError('vue_render_error', error);
        renderAppFallback();
    };

    app.use(createPinia()).mount('#app');
    appMounted = true;
} catch (error) {
    logClientError('app_boot_error', error);
    renderAppFallback();
}
