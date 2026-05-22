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
    const fridgeLoading = ref(false);

    const activeFridgeItemsCount = computed(() => fridgeItems.value.length);

    const loadFridgeData = async (token) => {
        fridgeLoading.value = true;

        try {
            const data = await fetchFridgeData(token);

            fridgeItems.value = data.items;
            fridgeHistory.value = data.history;

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
    };

    return {
        fridgeItems,
        fridgeHistory,
        fridgeLoading,
        activeFridgeItemsCount,
        loadFridgeData,
        eatOne,
        eatAll,
        discard,
        resetFridge,
    };
});
