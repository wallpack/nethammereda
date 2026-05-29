import { computed, ref } from 'vue';
import { defineStore } from 'pinia';
import {
    discardFridgeItem,
    eatAllFridgeItem,
    eatOneFridgeItem,
    fetchFridgeData,
} from '@/api/fridge';

export const useFridgeStore = defineStore('fridge', () => {
    const fridgeItems = ref([]);
    const fridgeHistory = ref([]);
    const fridgeMeta = ref({
        active_count: 0,
        total_portions: 0,
        expiring_soon_count: 0,
        eaten_today_count: 0,
    });
    const fridgeLoading = ref(false);

    const activeFridgeItemsCount = computed(() => fridgeItems.value.length);

    const loadFridgeData = async (token) => {
        fridgeLoading.value = true;

        try {
            const data = await fetchFridgeData(token);

            fridgeItems.value = data.items;
            fridgeHistory.value = data.history;
            fridgeMeta.value = {
                active_count: data.meta?.active_count ?? data.items.length,
                total_portions: data.meta?.total_portions ?? data.items.reduce((sum, item) => sum + (Number(item.quantity_remaining) || 0), 0),
                expiring_soon_count: data.meta?.expiring_soon_count ?? 0,
                eaten_today_count: data.meta?.eaten_today_count ?? 0,
            };

            return data;
        } finally {
            fridgeLoading.value = false;
        }
    };

    const eatOne = async (token, fridgeItemId) => {
        await eatOneFridgeItem(token, fridgeItemId);
        return loadFridgeData(token);
    };

    const eatAll = async (token, fridgeItemId) => {
        await eatAllFridgeItem(token, fridgeItemId);
        return loadFridgeData(token);
    };

    const discard = async (token, fridgeItemId) => {
        await discardFridgeItem(token, fridgeItemId);
        return loadFridgeData(token);
    };

    const resetFridge = () => {
        fridgeItems.value = [];
        fridgeHistory.value = [];
        fridgeMeta.value = {
            active_count: 0,
            total_portions: 0,
            expiring_soon_count: 0,
            eaten_today_count: 0,
        };
    };

    return {
        fridgeItems,
        fridgeHistory,
        fridgeMeta,
        fridgeLoading,
        activeFridgeItemsCount,
        loadFridgeData,
        eatOne,
        eatAll,
        discard,
        resetFridge,
    };
});
