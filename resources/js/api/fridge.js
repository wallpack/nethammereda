import { apiRequest } from './http';

export const fetchFridgeItems = (token) => apiRequest('/my-fridge', { token });

export const fetchFridgeHistory = (token) => apiRequest('/my-fridge/history', { token });

export const fetchFridgeData = async (token) => {
    const [activeResponse, historyResponse] = await Promise.all([
        fetchFridgeItems(token),
        fetchFridgeHistory(token),
    ]);

    return {
        items: activeResponse.data ?? [],
        history: historyResponse.data ?? [],
        meta: activeResponse.meta ?? {},
    };
};

export const eatOneFridgeItem = (token, fridgeItemId) => apiRequest(`/my-fridge/items/${fridgeItemId}/eat-one`, {
    method: 'PATCH',
    token,
});

export const eatAllFridgeItem = (token, fridgeItemId) => apiRequest(`/my-fridge/items/${fridgeItemId}/eat-all`, {
    method: 'PATCH',
    token,
});

export const discardFridgeItem = (token, fridgeItemId) => apiRequest(`/my-fridge/items/${fridgeItemId}/discard`, {
    method: 'PATCH',
    token,
});
