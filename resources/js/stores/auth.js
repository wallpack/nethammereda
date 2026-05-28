import { computed, ref, watch } from 'vue';
import { defineStore } from 'pinia';
import {
    fetchMe,
    loginWithPassword,
    loginWithTelegram,
    loginWithTelegramWidget,
    logoutUser,
    updateMyProfile,
} from '@/api/auth';

const tokenKey = 'lunch_mvp_token';
const requireFullNameKey = 'lunch_mvp_require_full_name';

const storedToken = () => localStorage.getItem(tokenKey) ?? sessionStorage.getItem(tokenKey) ?? '';
const storedRequireFullName = () => sessionStorage.getItem(requireFullNameKey) === '1';

export const useAuthStore = defineStore('auth', () => {
    const token = ref(storedToken());
    const requireFullName = ref(storedRequireFullName());
    const me = ref(null);
    const email = ref('');
    const password = ref('');
    const rememberMe = ref(Boolean(localStorage.getItem(tokenKey)));
    const showPassword = ref(false);
    const authLoading = ref(false);
    const authError = ref('');
    const profileSaving = ref(false);
    const profileError = ref('');

    const isAuthenticated = computed(() => Boolean(token.value && me.value));

    const displayUserName = computed(() => {
        return me.value?.full_name || me.value?.name || me.value?.first_name || me.value?.email || 'Пользователь';
    });

    const persistToken = () => {
        if (!token.value) {
            localStorage.removeItem(tokenKey);
            sessionStorage.removeItem(tokenKey);
            return;
        }

        if (rememberMe.value) {
            localStorage.setItem(tokenKey, token.value);
            sessionStorage.removeItem(tokenKey);
        } else {
            sessionStorage.setItem(tokenKey, token.value);
            localStorage.removeItem(tokenKey);
        }
    };

    watch(token, () => {
        persistToken();
        document.body.classList.toggle('app-authenticated', Boolean(token.value));
    }, { immediate: true });

    watch(rememberMe, () => {
        if (token.value) {
            persistToken();
        }
    });

    const setToken = (value) => {
        token.value = value ?? '';
    };

    const setRequireFullName = (value) => {
        requireFullName.value = value === true;

        if (requireFullName.value) {
            sessionStorage.setItem(requireFullNameKey, '1');
            return;
        }

        sessionStorage.removeItem(requireFullNameKey);
    };

    const hasTelegramInitData = () => {
        return Boolean(window.Telegram?.WebApp?.initData);
    };

    const waitForTelegramInitData = async (timeoutMs = 2500) => {
        if (hasTelegramInitData()) {
            return true;
        }

        if (typeof window === 'undefined') {
            return false;
        }

        const userAgent = window.navigator?.userAgent ?? '';
        const maybeTelegramContext = /Telegram/i.test(userAgent)
            || window.location.search.includes('tgWebAppData=')
            || window.location.hash.includes('tgWebAppData=');

        if (!maybeTelegramContext) {
            return false;
        }

        const startedAt = Date.now();

        while ((Date.now() - startedAt) < timeoutMs) {
            if (hasTelegramInitData()) {
                return true;
            }

            await new Promise((resolve) => setTimeout(resolve, 100));
        }

        return hasTelegramInitData();
    };

    const loadMe = async () => {
        const data = await fetchMe(token.value);
        me.value = data.data;

        const fullName = typeof me.value?.full_name === 'string' ? me.value.full_name.trim() : '';
        if (fullName !== '') {
            setRequireFullName(false);
        }

        return data;
    };

    const authWithTelegram = async () => {
        const initData = window.Telegram?.WebApp?.initData;

        if (!initData) {
            return false;
        }

        const response = await loginWithTelegram(initData, token.value);
        setToken(response.data.token);
        me.value = response.data.user ?? me.value;
        setRequireFullName(true);

        const fullName = typeof me.value?.full_name === 'string' ? me.value.full_name.trim() : '';
        if (fullName !== '') {
            setRequireFullName(false);
        }

        return true;
    };

    const authWithTelegramWidget = async (payload) => {
        const response = await loginWithTelegramWidget(payload, token.value);
        setToken(response.data.token);
        me.value = response.data.user ?? me.value;
        setRequireFullName(true);

        const fullName = typeof me.value?.full_name === 'string' ? me.value.full_name.trim() : '';
        if (fullName !== '') {
            setRequireFullName(false);
        }

        return response;
    };

    const authWithPassword = async () => {
        const response = await loginWithPassword({
            email: email.value,
            password: password.value,
        }, token.value);

        setToken(response.data.token);
        me.value = response.data.user ?? me.value;
        setRequireFullName(false);

        return response;
    };

    const requestLogout = async () => {
        return logoutUser(token.value);
    };

    const updateProfile = async ({ full_name }) => {
        profileSaving.value = true;
        profileError.value = '';

        try {
            const response = await updateMyProfile({ full_name }, token.value);
            me.value = response.data ?? me.value;
            const normalized = typeof me.value?.full_name === 'string' ? me.value.full_name.trim() : '';

            if (normalized !== '') {
                setRequireFullName(false);
            }

            return response;
        } catch (error) {
            profileError.value = error?.message ?? 'Не удалось обновить профиль.';
            throw error;
        } finally {
            profileSaving.value = false;
        }
    };

    const clearAuth = () => {
        setToken('');
        setRequireFullName(false);
        me.value = null;
        profileError.value = '';
    };

    return {
        token,
        requireFullName,
        me,
        email,
        password,
        rememberMe,
        showPassword,
        authLoading,
        authError,
        profileSaving,
        profileError,
        isAuthenticated,
        displayUserName,
        hasTelegramInitData,
        waitForTelegramInitData,
        loadMe,
        authWithTelegram,
        authWithTelegramWidget,
        authWithPassword,
        updateProfile,
        requestLogout,
        clearAuth,
        setRequireFullName,
    };
});
