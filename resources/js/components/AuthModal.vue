<script setup>
import { computed } from 'vue';
import {
    DialogClose,
    DialogContent,
    DialogOverlay,
    DialogPortal,
    DialogRoot,
    DialogTitle,
} from 'reka-ui';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Eye, EyeOff, Loader2, Send, UserRound, X } from 'lucide-vue-next';

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    email: {
        type: String,
        default: '',
    },
    password: {
        type: String,
        default: '',
    },
    rememberMe: {
        type: Boolean,
        default: false,
    },
    showPassword: {
        type: Boolean,
        default: false,
    },
    loading: {
        type: Boolean,
        default: false,
    },
    error: {
        type: String,
        default: '',
    },
    message: {
        type: String,
        default: '',
    },
});

const emit = defineEmits([
    'close',
    'submit',
    'update:email',
    'update:password',
    'update:remember-me',
    'update:show-password',
]);

const emailModel = computed({
    get: () => props.email,
    set: (value) => emit('update:email', value),
});

const passwordModel = computed({
    get: () => props.password,
    set: (value) => emit('update:password', value),
});

const rememberMeModel = computed({
    get: () => props.rememberMe,
    set: (value) => emit('update:remember-me', value === true),
});

const closeWhenChanged = (open) => {
    if (!open) {
        emit('close');
    }
};
</script>

<template>
    <DialogRoot :open="open" @update:open="closeWhenChanged">
        <DialogPortal>
            <DialogOverlay class="fixed inset-0 z-40 bg-slate-950/45" />
            <DialogContent
                class="customer-app fixed left-1/2 top-1/2 z-50 max-h-[calc(100dvh-2rem)] w-[min(calc(100%_-_2rem),30rem)] -translate-x-1/2 -translate-y-1/2 overflow-y-auto rounded-[1.75rem] border border-slate-200/70 bg-white px-5 pb-6 pt-7 text-slate-900 shadow-2xl outline-none sm:px-8"
            >
                <DialogClose as-child>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="absolute right-3 top-3 size-10 rounded-full text-slate-400 hover:bg-slate-100 hover:text-[#404040]"
                        aria-label="Закрыть окно входа"
                    >
                        <X aria-hidden="true" class="size-5" />
                    </Button>
                </DialogClose>

                <div class="mx-auto grid size-12 place-items-center rounded-2xl bg-blue-50 text-blue-700">
                    <UserRound aria-hidden="true" class="size-6" />
                </div>

                <DialogTitle class="customer-heading mt-4 text-center text-balance text-2xl leading-8">
                    Вход в аккаунт
                </DialogTitle>

                <Alert v-if="message" class="mt-5 rounded-xl border-blue-100 bg-blue-50 text-blue-800" role="status">
                    <AlertDescription>{{ message }}</AlertDescription>
                </Alert>

                <Alert
                    v-if="error"
                    id="auth-modal-error"
                    variant="destructive"
                    class="mt-5 rounded-xl border-red-200 bg-red-50 text-red-700"
                    role="alert"
                    aria-live="assertive"
                >
                    <AlertDescription>{{ error }}</AlertDescription>
                </Alert>

                <form class="mt-6 grid gap-4 text-left" @submit.prevent="emit('submit')">
                    <label class="grid gap-2">
                        <span class="customer-label">Почта <span aria-hidden="true" class="text-red-600">*</span></span>
                        <Input
                            v-model="emailModel"
                            id="auth-modal-email"
                            name="email"
                            type="email"
                            autocomplete="username"
                            required
                            autofocus
                            :aria-invalid="Boolean(error)"
                            :aria-describedby="error ? 'auth-modal-error' : undefined"
                            class="customer-input h-12 px-4 text-base focus-visible:border-blue-600 focus-visible:ring-blue-600/15"
                        />
                    </label>

                    <label class="grid gap-2">
                        <span class="customer-label">Пароль <span aria-hidden="true" class="text-red-600">*</span></span>
                        <div class="relative">
                            <Input
                                v-model="passwordModel"
                                id="auth-modal-password"
                                name="password"
                                :type="showPassword ? 'text' : 'password'"
                                autocomplete="current-password"
                                required
                                :aria-invalid="Boolean(error)"
                                :aria-describedby="error ? 'auth-modal-error' : undefined"
                                class="customer-input h-12 px-4 pr-12 text-base focus-visible:border-blue-600 focus-visible:ring-blue-600/15"
                            />
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="absolute right-0.5 top-0.5 size-11 rounded-full text-slate-500 hover:bg-slate-100 hover:text-[#404040]"
                                :aria-label="showPassword ? 'Скрыть пароль' : 'Показать пароль'"
                                @click="emit('update:show-password', !showPassword)"
                            >
                                <EyeOff v-if="showPassword" aria-hidden="true" class="size-5" />
                                <Eye v-else aria-hidden="true" class="size-5" />
                            </Button>
                        </div>
                    </label>

                    <label class="customer-label flex min-h-11 items-center gap-3">
                        <Checkbox
                            v-model="rememberMeModel"
                            class="size-5 rounded-md border-slate-300 bg-white data-checked:border-blue-700 data-checked:bg-blue-700 data-checked:text-white"
                        />
                        <span>Запомнить меня</span>
                    </label>

                    <Button
                        type="submit"
                        class="customer-cta mt-1 h-12 w-full text-sm"
                        :disabled="loading"
                    >
                        <Loader2 v-if="loading" aria-hidden="true" class="size-4 animate-spin" />
                        Войти
                    </Button>
                </form>

                <a
                    href="/auth/telegram"
                    class="mt-3 flex h-12 w-full items-center justify-center gap-2 rounded-full bg-[#229ED9] px-4 text-sm font-semibold text-white transition-[background-color,transform] duration-150 hover:bg-[#1D8BC1] active:scale-[0.98]"
                    data-testid="telegram-site-login-link"
                >
                    <Send aria-hidden="true" class="size-4" />
                    <span>Войти через Telegram</span>
                </a>
                <p class="sr-only" data-testid="telegram-site-login-disabled">
                    Вход через Telegram временно недоступен
                </p>
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>
