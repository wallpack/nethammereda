import { computed, ref, watch } from 'vue';
import { defineStore } from 'pinia';
import {
    fetchMe,
    loginWithPassword,
    loginWithTelegram,
    logoutUser,
    updateMyProfile,
} from '@/api/auth';

const tokenKey = 'lunch_mvp_token';

const storedToken = () => localStorage.getItem(tokenKey) ?? sessionStorage.getItem(tokenKey) ?? '';

export const useAuthStore = defineStore('auth', () => {
    const token = ref(storedToken());
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

    const hasTelegramInitData = () => {
        return Boolean(window.Telegram?.WebApp?.initData);
    };

    const loadMe = async () => {
        const data = await fetchMe(token.value);
        me.value = data.data;

        return data;
    };

    const authWithTelegram = async () => {
        const initData = window.Telegram?.WebApp?.initData;

        if (!initData) {
            return false;
        }

        const response = await loginWithTelegram(initData, token.value);
        setToken(response.data.token);

        return true;
    };

    const authWithPassword = async () => {
        const response = await loginWithPassword({
            email: email.value,
            password: password.value,
        }, token.value);

        setToken(response.data.token);
        me.value = response.data.user ?? me.value;

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
        me.value = null;
        profileError.value = '';
    };

    return {
        token,
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
        loadMe,
        authWithTelegram,
        authWithPassword,
        updateProfile,
        requestLogout,
        clearAuth,
    };
});
