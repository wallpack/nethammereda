import { computed, ref } from 'vue';
import { defineStore } from 'pinia';

export const useUiStore = defineStore('ui', () => {
    const loading = ref(true);
    const actionLoading = ref(false);
    const error = ref('');
    const info = ref('');
    const activeSidebarTab = ref('order');
    const isAuthModalOpen = ref(false);
    const authModalMessage = ref('');
    const isProfileModalOpen = ref(false);
    const favoriteIds = ref(new Set());
    const favoritesOnly = ref(false);
    const mobilePanel = ref(null);

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
        isProfileModalOpen.value = true;
    };

    const closeProfileModal = () => {
        isProfileModalOpen.value = false;
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

    const toggleFavoritesFilter = () => {
        favoritesOnly.value = !favoritesOnly.value;
    };

    const openMobilePanel = (panel) => {
        mobilePanel.value = panel;
    };

    const closeMobilePanel = () => {
        mobilePanel.value = null;
    };

    const resetSessionUi = () => {
        activeSidebarTab.value = 'order';
        favoriteIds.value = new Set();
        favoritesOnly.value = false;
        mobilePanel.value = null;
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
        favoriteIds,
        favoritesCount,
        favoritesOnly,
        mobilePanel,
        openAuthModal,
        closeAuthModal,
        openProfileModal,
        closeProfileModal,
        toggleFavorite,
        toggleFavoritesFilter,
        openMobilePanel,
        closeMobilePanel,
        resetSessionUi,
    };
});
