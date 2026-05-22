<script setup>
import { computed, onBeforeUnmount, onMounted } from 'vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Eye, EyeOff, Loader2, User, X } from 'lucide-vue-next';

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

const togglePassword = () => {
    emit('update:showPassword', !props.showPassword);
};

const onKeydown = (event) => {
    if (props.open && event.key === 'Escape') {
        emit('close');
    }
};

onMounted(() => {
    window.addEventListener('keydown', onKeydown);
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="open"
                class="fixed inset-0 z-[100] grid place-items-center bg-slate-950/45 px-4 py-8 backdrop-blur-[2px]"
                role="presentation"
                @click.self="emit('close')"
            >
                <section
                    class="relative w-full max-w-[520px] rounded-[22px] bg-white px-6 pb-7 pt-8 text-center text-slate-900 shadow-[0_28px_80px_rgba(15,23,42,0.22)] sm:px-9"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="auth-modal-title"
                >
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon-sm"
                        class="absolute right-4 top-4 h-10 w-10 rounded-full border border-[#e5ebf7] bg-white text-[#66769f] shadow-none hover:bg-[#f4f7ff] hover:text-[#111827]"
                        aria-label="Закрыть окно входа"
                        @click="emit('close')"
                    >
                        <X class="size-5" />
                    </Button>

                    <div class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-[#f1f5ff] text-[#2459d9]">
                        <User class="size-7" />
                    </div>

                    <h2 id="auth-modal-title" class="mt-5 text-[28px] font-black leading-tight tracking-[-0.4px] text-[#111827]">
                        Вход в аккаунт
                    </h2>

                    <p class="mx-auto mt-2 max-w-[340px] text-sm font-medium leading-5 text-[#66769f]">
                        Используйте данные пользователя из админ-панели Nethammer EDA.
                    </p>

                    <Alert
                        v-if="message"
                        class="mt-6 rounded-[12px] border-[#d7e2f7] bg-[#f4f7ff] text-left text-[#2459d9]"
                        role="status"
                    >
                        <AlertDescription>{{ message }}</AlertDescription>
                    </Alert>

                    <Alert
                        v-if="error"
                        variant="destructive"
                        class="mt-6 rounded-[12px] border-red-200 bg-red-50 text-left text-red-700"
                        role="alert"
                        aria-live="assertive"
                    >
                        <AlertDescription>{{ error }}</AlertDescription>
                    </Alert>

                    <form class="mt-6 grid gap-4 text-left" @submit.prevent="emit('submit')">
                        <label class="grid gap-2">
                            <span class="text-sm font-bold leading-5 text-[#25314d]">Почта<sup class="required-star">*</sup></span>
                            <Input
                                v-model="emailModel"
                                id="auth-modal-email"
                                name="email"
                                type="email"
                                autocomplete="username"
                                required
                                class="h-12 rounded-[12px] border-[#e1e8f5] bg-white px-4 text-[15px] font-medium text-[#1f2a44] placeholder:text-[#7080a3] focus-visible:border-[#0f52ff] focus-visible:ring-[#0f52ff]/15"
                            />
                        </label>

                        <label class="grid gap-2">
                            <span class="text-sm font-bold leading-5 text-[#25314d]">Пароль<sup class="required-star">*</sup></span>
                            <div class="relative">
                                <Input
                                    v-model="passwordModel"
                                    id="auth-modal-password"
                                    name="password"
                                    :type="showPassword ? 'text' : 'password'"
                                    autocomplete="current-password"
                                    required
                                    class="h-12 rounded-[12px] border-[#e1e8f5] bg-white px-4 pr-12 text-[15px] font-medium text-[#1f2a44] placeholder:text-[#7080a3] focus-visible:border-[#0f52ff] focus-visible:ring-[#0f52ff]/15"
                                />
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon-sm"
                                    class="absolute right-1.5 top-1.5 h-9 w-9 rounded-[10px] border-0 bg-transparent p-0 text-[#7080a3] shadow-none hover:bg-[#f4f7ff] hover:text-[#0f52ff]"
                                    :aria-label="showPassword ? 'Скрыть пароль' : 'Показать пароль'"
                                    @click="togglePassword"
                                >
                                    <Eye v-if="showPassword" class="size-5" />
                                    <EyeOff v-else class="size-5" />
                                </Button>
                            </div>
                        </label>

                        <label class="flex items-center gap-3 text-sm font-semibold leading-5 text-[#25314d]">
                            <Checkbox
                                v-model="rememberMeModel"
                                class="size-4 rounded-[4px] border-[#d9e3f3] bg-white data-checked:border-[#0f52ff] data-checked:bg-[#0f52ff] data-checked:text-white"
                            />
                            <span>Запомнить меня</span>
                        </label>

                        <Button
                            type="submit"
                            class="h-12 w-full rounded-[999px] bg-[#111827] text-[15px] font-bold text-white shadow-[0_14px_26px_rgba(17,24,39,0.18)] hover:bg-[#0f172a]"
                            :disabled="loading"
                        >
                            <Loader2 v-if="loading" class="mr-2 size-4 animate-spin" />
                            Войти
                        </Button>
                    </form>

                    <Button
                        v-if="showTelegram"
                        type="button"
                        variant="outline"
                        class="mt-3 h-11 w-full rounded-[999px] border-[#d9e3f3] bg-white text-sm font-bold text-[#25314d] hover:bg-[#f4f7ff]"
                        :disabled="loading"
                        @click="emit('telegram-login')"
                    >
                        <Loader2 v-if="loading" class="mr-2 size-4 animate-spin" />
                        Войти через Telegram
                    </Button>
                </section>
            </div>
        </Transition>
    </Teleport>
</template>
