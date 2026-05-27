import { apiRequest } from './http';

export const fetchMe = (token) => apiRequest('/me', { token });
export const updateMyProfile = ({ full_name }, token) => apiRequest('/me/profile', {
    method: 'PATCH',
    token,
    body: { full_name },
});

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

export const fetchTelegramLinkStatus = (token) => apiRequest('/telegram/link-status', { token });

export const createTelegramLinkToken = (token) => apiRequest('/telegram/link-token', {
    method: 'POST',
    token,
});
