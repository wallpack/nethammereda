<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    botUsername: {
        type: String,
        default: '',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['auth', 'error']);

const callbackName = '__telegramSiteLoginCallback';
const widgetRoot = ref(null);

const registerCallback = () => {
    window[callbackName] = (user) => {
        emit('auth', user);
    };
};

const clearWidget = () => {
    if (widgetRoot.value) {
        widgetRoot.value.innerHTML = '';
    }
};

const renderWidget = () => {
    clearWidget();

    if (!widgetRoot.value || !props.botUsername) {
        return;
    }

    const script = document.createElement('script');
    script.src = 'https://telegram.org/js/telegram-widget.js?22';
    script.async = true;
    script.setAttribute('data-telegram-login', props.botUsername);
    script.setAttribute('data-size', 'large');
    script.setAttribute('data-userpic', 'false');
    script.setAttribute('data-lang', 'ru');
    script.setAttribute('data-request-access', 'write');
    script.setAttribute('data-onauth', `${callbackName}(user)`);
    script.onerror = () => emit('error');

    widgetRoot.value.appendChild(script);
};

onMounted(() => {
    registerCallback();
    renderWidget();
});

watch(() => props.botUsername, () => {
    registerCallback();
    renderWidget();
});

onBeforeUnmount(() => {
    if (window[callbackName]) {
        delete window[callbackName];
    }
});
</script>

<template>
    <div class="relative">
        <div
            ref="widgetRoot"
            data-testid="telegram-login-widget"
            class="flex min-h-12 items-center justify-center"
        />
        <div
            v-if="disabled"
            class="absolute inset-0 cursor-not-allowed rounded-xl bg-white/70"
            aria-hidden="true"
        />
    </div>
</template>
