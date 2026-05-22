import { computed, ref } from 'vue';
import { defineStore } from 'pinia';

export const useUiStore = defineStore('ui', () => {
    const loading = ref(false);
    const actionLoading = ref(false);
    const error = ref('');
    const info = ref('');
    const activeSidebarTab = ref('order');
    const isAuthModalOpen = ref(false);
    const authModalMessage = ref('');
    const isProfileModalOpen = ref(false);
    const profileNotice = ref('');
    const favoriteIds = ref(new Set());

    const favoritesCount = computed(() => favoriteIds.value.size);

    const openAuthModal = (message = '') => {
        authModalMessage.value = message;
        error.value = '';
        isAuthModalOpen.value = true;
    };

    const closeAuthModal = () => {
        isAuthModalOpen.value = false;
        authModalMessage.value = '';
    };

    const openProfileModal = () => {
        profileNotice.value = '';
        isProfileModalOpen.value = true;
    };

    const closeProfileModal = () => {
        isProfileModalOpen.value = false;
        profileNotice.value = '';
    };

    const showProfileNotice = (message) => {
        profileNotice.value = message;
    };

    const toggleFavorite = (menuItemId) => {
        const nextFavorites = new Set(favoriteIds.value);

        if (nextFavorites.has(menuItemId)) {
            nextFavorites.delete(menuItemId);
        } else {
            nextFavorites.add(menuItemId);
        }

        favoriteIds.value = nextFavorites;
    };

    const resetSessionUi = () => {
        activeSidebarTab.value = 'order';
        favoriteIds.value = new Set();
        profileNotice.value = '';
    };

    return {
        loading,
        actionLoading,
        error,
        info,
        activeSidebarTab,
        isAuthModalOpen,
        authModalMessage,
        isProfileModalOpen,
        profileNotice,
        favoriteIds,
        favoritesCount,
        openAuthModal,
        closeAuthModal,
        openProfileModal,
        closeProfileModal,
        showProfileNotice,
        toggleFavorite,
        resetSessionUi,
    };
});
