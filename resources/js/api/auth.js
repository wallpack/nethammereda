import { apiRequest } from './http';

export const fetchMe = (token) => apiRequest('/me', { token });

export const loginWithPassword = ({ email, password }, token = '') => apiRequest('/auth/login', {
    method: 'POST',
    token,
    body: { email, password },
});

export const loginWithTelegram = (initData, token = '') => apiRequest('/auth/telegram', {
    method: 'POST',
    token,
    body: { init_data: initData },
});

export const logoutUser = (token) => apiRequest('/auth/logout', {
    method: 'POST',
    token,
});
