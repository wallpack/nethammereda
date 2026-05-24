import { apiRequest } from './http';

export const fetchMyOrder = (token) => apiRequest('/my-order', { token });

export const addOrderItem = (token, menuItemId, quantity = 1) => apiRequest('/my-order/items', {
    method: 'POST',
    token,
    body: {
        menu_item_id: menuItemId,
        quantity,
    },
});

export const updateOrderItemQuantity = (token, orderItemId, quantity) => apiRequest(`/my-order/items/${orderItemId}`, {
    method: 'PATCH',
    token,
    body: { quantity },
});

export const deleteOrderItem = (token, orderItemId) => apiRequest(`/my-order/items/${orderItemId}`, {
    method: 'DELETE',
    token,
});

export const submitMyOrder = (token) => apiRequest('/my-order/submit', {
    method: 'POST',
    token,
});

export const reopenMyOrder = (token) => apiRequest('/my-order/reopen', {
    method: 'POST',
    token,
});
