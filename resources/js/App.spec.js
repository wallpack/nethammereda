import { flushPromises, mount } from '@vue/test-utils';
import { createPinia } from 'pinia';
import { nextTick } from 'vue';
import { describe, expect, it, vi } from 'vitest';
import App from './App.vue';

const user = {
    id: 7,
    name: 'Тестовый пользователь',
    email: 'user@lunch.local',
    role: 'user',
};

const cycle = {
    id: 3,
    title: 'Тестовая неделя',
    starts_at: '2026-05-18T00:00:00.000000Z',
    closes_at: '2026-05-22T12:00:00.000000Z',
    status: 'open',
    is_open_for_ordering: true,
};

const category = {
    id: 1,
    name: 'Супы',
    sort_order: 10,
};

const menuItem = {
    id: 11,
    category_id: category.id,
    category,
    title: 'Суп с курицей',
    description: 'Легкий обед',
    composition: 'Курица, овощи',
    weight: '300 г',
    calories: 220,
    proteins: 18,
    fats: 8,
    carbs: 16,
    price: '250.00',
    image_url: null,
    is_active: true,
};

const emptyOrder = {
    id: 15,
    user_id: user.id,
    status: 'draft',
    total_price: '0.00',
    items: [],
};

const orderWithItem = {
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

const jsonResponse = (payload, status = 200) => Promise.resolve({
    ok: status >= 200 && status < 300,
    status,
    json: () => Promise.resolve(payload),
});

const createFetchMock = ({ authenticated = false, order = emptyOrder } = {}) => {
    let currentOrder = order;

    return vi.fn((input, options = {}) => {
        const path = String(input).replace('/api', '');
        const method = options.method ?? 'GET';

        if (path === '/me') {
            return authenticated
                ? jsonResponse({ data: user })
                : jsonResponse({ message: 'Unauthenticated.' }, 401);
        }

        if (path === '/current-cycle') {
            return jsonResponse({ data: cycle });
        }

        if (path === '/menu/categories') {
            return jsonResponse({ data: [category] });
        }

        if (path === '/menu/items') {
            return jsonResponse({ data: [menuItem] });
        }

        if (path === '/my-order' && method === 'GET') {
            return jsonResponse({ data: { cycle, order: currentOrder } });
        }

        if (path === '/my-order/items' && method === 'POST') {
            currentOrder = orderWithItem;
            return jsonResponse({ data: currentOrder });
        }

        if (path === '/my-fridge') {
            return jsonResponse({ data: [] });
        }

        if (path === '/my-fridge/history') {
            return jsonResponse({ data: [] });
        }

        if (path === '/auth/logout' && method === 'POST') {
            return jsonResponse({ data: { ok: true } });
        }

        return jsonResponse({ data: null });
    });
};

const mountApp = async ({ authenticated = false, order = emptyOrder } = {}) => {
    if (authenticated) {
        localStorage.setItem('lunch_mvp_token', 'test-token');
    }

    const fetchMock = createFetchMock({ authenticated, order });
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

const buttonByText = (text) => {
    return Array.from(document.querySelectorAll('button')).find((button) => button.textContent.includes(text));
};

const click = async (element) => {
    expect(element).toBeTruthy();
    element.click();
    await nextTick();
    await flushPromises();
};

const postedTo = (fetchMock, path) => {
    return fetchMock.mock.calls.some(([url, options = {}]) => String(url).includes(path) && options.method === 'POST');
};

describe('catalog auth UX', () => {
    it('renders guest header with a single login action and no old guest copy', async () => {
        await mountApp();

        expect(buttonByText('Войти')).toBeTruthy();
        expect(document.body.textContent).not.toContain('Гость');
        expect(document.body.textContent).not.toContain('Вход в панели заказа');
    });

    it('opens and closes login modal from the header', async () => {
        await mountApp();

        await click(buttonByText('Войти'));

        expect(document.body.textContent).toContain('Вход в аккаунт');
        expect(document.querySelector('#auth-modal-email')).toBeTruthy();
        expect(document.querySelector('#auth-modal-password')).toBeTruthy();
        expect(buttonByText('Войти')).toBeTruthy();

        await click(document.querySelector('[aria-label="Закрыть окно входа"]'));

        expect(document.body.textContent).not.toContain('Вход в аккаунт');
    });

    it('hides the order panel and sidebar login form for guests', async () => {
        await mountApp();

        expect(document.querySelector('.catalog-order-panel')).toBeNull();
        expect(document.body.textContent).not.toContain('ЗАКАЗ');
        expect(document.body.textContent).not.toContain('Войдите для корзины');
        expect(document.querySelector('#catalog-login-email')).toBeNull();
    });

    it('keeps product CTA as Add for guests and opens login modal instead of adding to cart', async () => {
        const { fetchMock } = await mountApp();

        await click(buttonByText('Добавить'));

        expect(postedTo(fetchMock, '/my-order/items')).toBe(false);
        expect(document.body.textContent).toContain('Вход в аккаунт');
        expect(document.body.textContent).toContain('Войдите, чтобы добавить блюдо в заказ.');
    });

    it('opens login modal from favorite heart for guests without a favorite request', async () => {
        const { fetchMock } = await mountApp();
        const favoriteButton = document.querySelector('[aria-label^="Добавить в избранное"]');

        await click(favoriteButton);

        expect(postedTo(fetchMock, '/favorites')).toBe(false);
        expect(document.body.textContent).toContain('Вход в аккаунт');
        expect(document.body.textContent).toContain('Войдите, чтобы добавить блюдо в избранное.');
    });

    it('renders authenticated header with the user name and order panel', async () => {
        await mountApp({ authenticated: true });

        expect(document.body.textContent).toContain(user.name);
        expect(buttonByText('Войти')).toBeFalsy();
        expect(document.querySelector('.catalog-order-panel')).toBeTruthy();
        expect(document.body.textContent).toContain('Ваш заказ');
    });

    it('opens profile modal with account actions', async () => {
        await mountApp({ authenticated: true });

        await click(buttonByText(user.name));

        expect(document.body.textContent).toContain(user.name);
        expect(document.body.textContent).toContain(user.email);
        expect(document.body.textContent).toContain('Избранное');
        expect(document.body.textContent).toContain('Мои заказы');
        expect(buttonByText('Выйти')).toBeTruthy();
    });

    it('logs out from profile and returns catalog to guest state', async () => {
        const { fetchMock } = await mountApp({ authenticated: true });

        await click(buttonByText(user.name));
        await click(buttonByText('Выйти'));

        expect(postedTo(fetchMock, '/auth/logout')).toBe(true);
        expect(document.body.textContent).not.toContain(user.name);
        expect(buttonByText('Войти')).toBeTruthy();
        expect(document.querySelector('.catalog-order-panel')).toBeNull();
    });

    it('lets authenticated users add items to the order', async () => {
        const { fetchMock } = await mountApp({ authenticated: true });

        await click(buttonByText('Добавить'));

        expect(postedTo(fetchMock, '/my-order/items')).toBe(true);
        expect(document.body.textContent).toContain('1 товаров');
    });
});
