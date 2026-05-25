<script setup>
import { computed } from 'vue';
import {
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogOverlay,
    DialogPortal,
    DialogRoot,
    DialogTitle,
} from 'reka-ui';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Eye, EyeOff, Loader2, UserRound, X } from 'lucide-vue-next';

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
    showTelegram: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits([
    'close',
    'submit',
    'telegram-login',
    'update:email',
    'update:password',
    'update:rememberMe',
    'update:showPassword',
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
    set: (value) => emit('update:rememberMe', value === true),
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
                class="fixed left-1/2 top-1/2 z-50 max-h-[calc(100dvh-2rem)] w-[min(calc(100%_-_2rem),30rem)] -translate-x-1/2 -translate-y-1/2 overflow-y-auto rounded-3xl border border-slate-200 bg-white px-5 pb-6 pt-7 text-slate-900 shadow-xl outline-none sm:px-8"
            >
                <DialogClose as-child>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="absolute right-3 top-3 size-11 rounded-xl text-slate-500 hover:bg-slate-100 hover:text-slate-900"
                        aria-label="Закрыть окно входа"
                    >
                        <X aria-hidden="true" class="size-5" />
                    </Button>
                </DialogClose>

                <div class="mx-auto grid size-12 place-items-center rounded-xl bg-blue-50 text-blue-700">
                    <UserRound aria-hidden="true" class="size-6" />
                </div>

                <DialogTitle class="mt-5 text-center text-balance text-2xl font-semibold text-slate-950">
                    Вход в аккаунт
                </DialogTitle>
                <DialogDescription class="mx-auto mt-2 max-w-sm text-center text-pretty text-sm leading-6 text-slate-500">
                    Войдите, чтобы собрать заказ и проверить холодильник.
                </DialogDescription>

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
                        <span class="text-sm font-medium text-slate-700">Почта <span aria-hidden="true" class="text-red-600">*</span></span>
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
                            class="h-12 rounded-xl border-slate-200 bg-white px-4 text-base text-slate-900 placeholder:text-slate-400 focus-visible:border-blue-600 focus-visible:ring-blue-600/15"
                        />
                    </label>

                    <label class="grid gap-2">
                        <span class="text-sm font-medium text-slate-700">Пароль <span aria-hidden="true" class="text-red-600">*</span></span>
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
                                class="h-12 rounded-xl border-slate-200 bg-white px-4 pr-12 text-base text-slate-900 focus-visible:border-blue-600 focus-visible:ring-blue-600/15"
                            />
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="absolute right-0.5 top-0.5 size-11 rounded-xl text-slate-500 hover:bg-slate-100 hover:text-slate-900"
                                :aria-label="showPassword ? 'Скрыть пароль' : 'Показать пароль'"
                                @click="emit('update:show-password', !showPassword)"
                            >
                                <EyeOff v-if="showPassword" aria-hidden="true" class="size-5" />
                                <Eye v-else aria-hidden="true" class="size-5" />
                            </Button>
                        </div>
                    </label>

                    <label class="flex min-h-11 items-center gap-3 text-sm font-medium text-slate-700">
                        <Checkbox
                            v-model="rememberMeModel"
                            class="size-5 rounded-md border-slate-300 bg-white data-checked:border-blue-700 data-checked:bg-blue-700 data-checked:text-white"
                        />
                        <span>Запомнить меня</span>
                    </label>

                    <Button
                        type="submit"
                        class="mt-1 h-12 w-full rounded-xl bg-blue-700 text-sm font-semibold text-white transition-[background-color,transform] duration-150 hover:bg-blue-800 active:scale-[0.98]"
                        :disabled="loading"
                    >
                        <Loader2 v-if="loading" aria-hidden="true" class="size-4 animate-spin" />
                        Войти
                    </Button>
                </form>

                <Button
                    v-if="showTelegram"
                    type="button"
                    variant="outline"
                    class="mt-3 h-12 w-full rounded-xl border-slate-200 bg-white text-sm font-semibold text-slate-700 hover:bg-slate-50"
                    :disabled="loading"
                    @click="emit('telegram-login')"
                >
                    Войти через Telegram
                </Button>
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>
