<script setup>
import { computed, ref } from 'vue';
import { withTimeout } from '@/lib/async';

const props = defineProps({
    botId: {
        type: [Number, String, null],
        default: null,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['auth', 'error']);

let telegramLoginScriptPromise;
const SCRIPT_LOAD_TIMEOUT_MS = 5000;

const isOpening = ref(false);

const normalizedBotId = computed(() => {
    const parsed = Number.parseInt(String(props.botId ?? ''), 10);

    if (!Number.isFinite(parsed) || parsed <= 0) {
        return null;
    }

    return parsed;
});

const isButtonDisabled = computed(() => {
    return props.disabled || isOpening.value || normalizedBotId.value === null;
});

const scriptErrorCodes = new Set([
    'telegram_script_load_error',
    'telegram_script_timeout',
    'telegram_api_unavailable',
    'telegram_env_unavailable',
]);

const normalizeWidgetErrorReason = (error) => {
    if (error instanceof Error && typeof error.message === 'string' && error.message !== '') {
        return error.message;
    }

    return 'telegram_login_failed';
};

const ensureTelegramLoginScript = async () => {
    if (typeof window === 'undefined') {
        throw new Error('telegram_env_unavailable');
    }

    if (typeof window.Telegram?.Login?.auth === 'function') {
        return;
    }

    if (!telegramLoginScriptPromise) {
        telegramLoginScriptPromise = new Promise((resolve, reject) => {
            const existingScript = document.querySelector('script[src^="https://telegram.org/js/telegram-widget.js"]');
            if (existingScript) {
                if (typeof window.Telegram?.Login?.auth === 'function') {
                    resolve();
                    return;
                }

                existingScript.addEventListener('load', () => resolve(), { once: true });
                existingScript.addEventListener('error', () => reject(new Error('telegram_script_load_error')), { once: true });
                return;
            }

            const script = document.createElement('script');
            script.src = 'https://telegram.org/js/telegram-widget.js?22';
            script.async = true;
            script.onload = () => resolve();
            script.onerror = () => reject(new Error('telegram_script_load_error'));
            document.head.appendChild(script);
        });
    }

    try {
        await withTimeout(telegramLoginScriptPromise, SCRIPT_LOAD_TIMEOUT_MS, 'telegram_script_timeout');
    } catch (error) {
        telegramLoginScriptPromise = undefined;
        throw error;
    }

    if (typeof window.Telegram?.Login?.auth !== 'function') {
        throw new Error('telegram_api_unavailable');
    }
};

const normalizeAuthPayload = (rawPayload) => {
    if (!rawPayload || typeof rawPayload !== 'object') {
        return null;
    }

    if ('id' in rawPayload && 'hash' in rawPayload && 'auth_date' in rawPayload) {
        return rawPayload;
    }

    if (
        'user' in rawPayload
        && rawPayload.user
        && typeof rawPayload.user === 'object'
        && 'hash' in rawPayload
        && 'auth_date' in rawPayload
    ) {
        const user = rawPayload.user;

        return {
            id: user.id,
            first_name: user.first_name,
            last_name: user.last_name,
            username: user.username,
            photo_url: user.photo_url,
            auth_date: rawPayload.auth_date,
            hash: rawPayload.hash,
        };
    }

    return null;
};

const openTelegramLogin = async () => {
    if (isButtonDisabled.value) {
        return;
    }

    isOpening.value = true;

    try {
        await ensureTelegramLoginScript();

        const result = await new Promise((resolve) => {
            window.Telegram.Login.auth({
                bot_id: normalizedBotId.value,
                request_access: 'write',
                lang: 'ru',
            }, (authData) => {
                resolve(authData);
            });
        });

        const normalizedPayload = normalizeAuthPayload(result);

        if (!normalizedPayload) {
            emit('error', { reason: 'invalid_payload' });
            return;
        }

        emit('auth', normalizedPayload);
    } catch (error) {
        const reason = normalizeWidgetErrorReason(error);

        if (scriptErrorCodes.has(reason)) {
            console.warn('telegram_site_login_script_failed', {
                reason,
            });
        }

        emit('error', { reason });
    } finally {
        isOpening.value = false;
    }
};
</script>

<template>
    <button
        type="button"
        data-testid="telegram-site-login-button"
        class="mt-1 h-12 w-full rounded-xl bg-[#229ED9] px-4 text-sm font-semibold text-white transition-[background-color,transform] duration-150 hover:bg-[#1D8BC1] active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-60"
        :disabled="isButtonDisabled"
        @click="openTelegramLogin"
    >
        <span class="flex items-center justify-center gap-2">
            <svg
                aria-hidden="true"
                viewBox="0 0 24 24"
                class="size-5"
                fill="currentColor"
            >
                <path d="M21.425 4.574a1 1 0 0 0-1.052-.152l-17 7a1 1 0 0 0 .094 1.886l4.607 1.535 1.535 4.607a1 1 0 0 0 1.886.094l7-17a1 1 0 0 0-.152-1.052 1 1 0 0 0-1.052-.153l-10.932 4.56a1 1 0 0 0 .186 1.909l4.638.928.928 4.638a1 1 0 0 0 1.909.186l4.56-10.932a1 1 0 0 0-.153-1.052z" />
            </svg>
            <span>{{ isOpening ? 'Открываем Telegram...' : 'Войти через Telegram' }}</span>
        </span>
    </button>
</template>
