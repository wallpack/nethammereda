export const apiRequest = async (path, options = {}) => {
    const headers = {
        Accept: 'application/json',
        ...(options.headers ?? {}),
    };

    if (options.token) {
        headers.Authorization = `Bearer ${options.token}`;
    }

    const payload = {
        method: options.method ?? 'GET',
        headers,
    };

    if (options.body !== undefined) {
        headers['Content-Type'] = 'application/json';
        payload.body = JSON.stringify(options.body);
    }

    const response = await fetch(`/api${path}`, payload);
    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        throw new Error(data.message ?? 'Ошибка запроса');
    }

    return data;
};
