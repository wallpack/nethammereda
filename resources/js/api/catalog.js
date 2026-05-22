import { apiRequest } from './http';

export const fetchCurrentCycle = () => apiRequest('/current-cycle');

export const fetchMenuCategories = () => apiRequest('/menu/categories');

export const fetchMenuItems = () => apiRequest('/menu/items');

export const fetchCatalogData = async () => {
    const [cycleResponse, categoriesResponse, itemsResponse] = await Promise.all([
        fetchCurrentCycle(),
        fetchMenuCategories(),
        fetchMenuItems(),
    ]);

    return {
        cycle: cycleResponse.data,
        categories: categoriesResponse.data ?? [],
        items: itemsResponse.data ?? [],
    };
};
