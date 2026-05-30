import { flushPromises, mount } from '@vue/test-utils';
import { createPinia } from 'pinia';
import { describe, expect, it, vi } from 'vitest';
import App from './App.vue';

const user = {
    id: 7,
    name: 'Test User',
    email: 'user@lunch.local',
    role: 'user',
};

const cycleOpen = {
    id: 3,
    title: 'Тестовая неделя',
    closes_at: '2026-05-30T12:00:00.000000Z',
    deadline_date: '30.05',
    deadline_time: '12:00',
    deadline_display: '30.05, 12:00',
    status: 'open',
    is_open_for_ordering: true,
    is_orderable: true,
    can_order: true,
    deadline_passed: false,
};

const category = { id: 1, name: 'Категория', sort_order: 10 };

const menuItem = {
    id: 11,
    category_id: category.id,
    category,
    title: 'Котлета',
    weight: '300 г',
    calories: 220,
    proteins: 18,
    fats: 8,
    carbs: 16,
    price: '250.00',
    image_url: null,
    image_display_url: null,
    is_active: true,
};

const emptyOrder = {
    id: 15,
    user_id: user.id,
    status: 'draft',
    total_price: '0.00',
    items: [],
};

const filledOrder = {
    ...emptyOrder,
    total_price: '250.00',
    items: [
        {
            id: 21,
            menu_item_id: menuItem.id,
            title_snapshot: menuItem.title,
            price_snapshot: menuItem.price,
            quantity: 1,
            status: 'ordered',
        },
    ],
};

const historyOrder = {
    id: 91,
    status: 'submitted',
    submitted_at: '2026-05-29T10:00:00.000000Z',
    total_price: '500.00',
    items_count: 2,
    can_repeat: true,
    items: [
        { id: 901, title: 'Котлета', quantity: 1 },
        { id: 902, title: 'Гречка', quantity: 1 },
    ],
};

const jsonResponse = (payload, status = 200) => Promise.resolve({
    ok: status >= 200 && status < 300,
    status,
    json: () => Promise.resolve(payload),
});

const postedTo = (fetchMock, path) => {
    return fetchMock.mock.calls.some(([url, options = {}]) => String(url).includes(path) && options.method === 'POST');
};

const createFetchMock = ({
    order = emptyOrder,
    currentCycle = cycleOpen,
    orderHistory = [historyOrder],
    repeatOrderData = filledOrder,
    repeatOrderMessage = 'Заказ добавлен в корзину.',
    repeatOrderWarning = null,
    repeatOrderSkippedItems = [],
} = {}) => {
    let currentOrder = order;

    return vi.fn((input, options = {}) => {
        const path = String(input).replace('/api', '');
        const method = options.method ?? 'GET';

        if (path === '/me') {
            return jsonResponse({ data: user });
        }

        if (path === '/current-cycle') {
            return jsonResponse({ data: currentCycle });
        }

        if (path === '/menu/categories') {
            return jsonResponse({ data: [category] });
        }

        if (path === '/menu/items') {
            return jsonResponse({ data: [menuItem] });
        }

        if (path === '/my-order' && method === 'GET') {
            return jsonResponse({
                data: {
                    cycle: currentCycle,
                    order: currentOrder,
                    draft_unavailable: false,
                    draft_unavailable_message: null,
                },
            });
        }

        if (path === '/my-orders/history' && method === 'GET') {
            return jsonResponse({ data: orderHistory });
        }

        if (path === '/my-fridge' && method === 'GET') {
            return jsonResponse({ data: [] });
        }

        if (path === '/my-fridge/history' && method === 'GET') {
            return jsonResponse({ data: [] });
        }

        if (/^\/my-orders\/\d+\/repeat$/.test(path) && method === 'POST') {
            currentOrder = repeatOrderData;

            return jsonResponse({
                data: {
                    order: repeatOrderData,
                    skipped_items: repeatOrderSkippedItems,
                    message: repeatOrderMessage,
                    warning: repeatOrderWarning,
                },
            });
        }

        if (path === '/telegram/link-status' && method === 'GET') {
            return jsonResponse({
                data: {
                    linked: false,
                    link_available: true,
                    bot_link: 'https://t.me/lunch_demo_bot',
                    bot_username: 'lunch_demo_bot',
                },
            });
        }

        return jsonResponse({ data: null });
    });
};

const mountApp = async (options = {}) => {
    localStorage.setItem('lunch_mvp_token', 'test-token');
    const fetchMock = createFetchMock(options);
    global.fetch = fetchMock;

    const wrapper = mount(App, {
        attachTo: document.body,
        global: {
            plugins: [createPinia()],
        },
    });

    await flushPromises();
    await flushPromises();

    return { wrapper, fetchMock };
};

describe('App profile order-history repeat flow', () => {
    it('repeats order from profile history when cart is empty without a routine success banner', async () => {
        const { fetchMock } = await mountApp({
            order: emptyOrder,
            orderHistory: [historyOrder],
            repeatOrderData: filledOrder,
        });

        const profileButton = document.querySelector('[aria-label^="Открыть профиль:"]');
        expect(profileButton).toBeTruthy();
        profileButton.click();
        await flushPromises();

        const orderedTab = document.querySelector('[data-testid="profile-tab-ordered"]');
        expect(orderedTab).toBeTruthy();
        orderedTab.click();
        await flushPromises();

        const repeatButton = document.querySelector('[data-testid="profile-repeat-order-button"]');
        expect(repeatButton).toBeTruthy();
        repeatButton.click();
        await flushPromises();

        expect(postedTo(fetchMock, `/my-orders/${historyOrder.id}/repeat`)).toBe(true);
        expect(document.body.textContent).not.toContain('Заказ добавлен в корзину.');
        expect(document.querySelector('[role="status"]')).toBeNull();
        expect(document.querySelector('[data-testid="order-panel-item"]')?.textContent).toContain('Котлета');
    });

    it('asks confirm before replace when current cart is not empty and can be cancelled', async () => {
        const confirmMock = vi.spyOn(window, 'confirm').mockReturnValueOnce(false);
        const { fetchMock } = await mountApp({
            order: filledOrder,
            orderHistory: [historyOrder],
        });

        const profileButton = document.querySelector('[aria-label^="Открыть профиль:"]');
        profileButton.click();
        await flushPromises();

        document.querySelector('[data-testid="profile-tab-ordered"]').click();
        await flushPromises();

        document.querySelector('[data-testid="profile-repeat-order-button"]').click();
        await flushPromises();

        expect(confirmMock).toHaveBeenCalled();
        expect(postedTo(fetchMock, `/my-orders/${historyOrder.id}/repeat`)).toBe(false);
        confirmMock.mockRestore();
    });

    it('shows skipped items warning after repeat', async () => {
        const { fetchMock } = await mountApp({
            order: emptyOrder,
            orderHistory: [historyOrder],
            repeatOrderData: filledOrder,
            repeatOrderSkippedItems: ['Салат овощной'],
            repeatOrderWarning: 'Некоторые блюда сейчас недоступны.',
        });

        document.querySelector('[aria-label^="Открыть профиль:"]').click();
        await flushPromises();
        document.querySelector('[data-testid="profile-tab-ordered"]').click();
        await flushPromises();
        document.querySelector('[data-testid="profile-repeat-order-button"]').click();
        await flushPromises();

        expect(postedTo(fetchMock, `/my-orders/${historyOrder.id}/repeat`)).toBe(true);
        expect(document.body.textContent).toContain('Некоторые блюда сейчас недоступны: Салат овощной.');
    });
});
