import { flushPromises, mount } from '@vue/test-utils';
import { createPinia } from 'pinia';
import { nextTick } from 'vue';
import { describe, expect, it, vi } from 'vitest';
import App from './App.vue';

const user = {
    id: 7,
    name: 'Тестовый пользователь',
    email: 'user@lunch.local',
    telegram_id: null,
    role: 'user',
};

const cycle = {
    id: 3,
    title: 'Тестовая неделя',
    starts_at: '2026-05-18T00:00:00.000000Z',
    closes_at: '2026-05-22T12:00:00.000000Z',
    deadline_date: '22.05',
    deadline_time: '12:00',
    deadline_display: '22.05, 12:00',
    deadline_display_full: '22.05.2026, 12:00',
    status: 'open',
    is_open_for_ordering: true,
};

const category = {
    id: 1,
    name: 'Супы',
    sort_order: 10,
};

const secondCategory = {
    id: 2,
    name: 'Горячее',
    sort_order: 20,
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
    image_display_url: null,
    is_active: true,
};

const secondMenuItem = {
    ...menuItem,
    id: 12,
    category_id: secondCategory.id,
    category: secondCategory,
    title: 'Котлета с пюре',
    description: 'Сытное горячее блюдо',
    composition: 'Говядина, картофель',
    price: '340.00',
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

const submittedOrder = {
    ...orderWithItem,
    status: 'submitted',
    can_reopen_for_editing: true,
};

const fridgeItem = {
    id: 31,
    user_id: user.id,
    title_snapshot: 'Котлета с пюре',
    quantity_total: 2,
    quantity_remaining: 2,
    status: 'in_fridge',
    expires_at: '2026-05-26T09:30:00.000000Z',
};

const fridgeHistoryItem = {
    ...fridgeItem,
    id: 32,
    quantity_remaining: 0,
    status: 'eaten',
};

const closedOrderingMessage = 'Приём заказов закрыт.';
const closedOrderingCartClearedMessage = 'Приём заказов закрыт.';
const draftUnavailableMessage = 'Цикл закрыт, черновик заказа больше недоступен.';
const closedOrderingInfoMessage = 'Приём заказов закрыт.';

const jsonResponse = (payload, status = 200) => Promise.resolve({
    ok: status >= 200 && status < 300,
    status,
    json: () => Promise.resolve(payload),
});

const createFetchMock = ({
    authenticated = false,
    authenticatedUser = user,
    profilePatchStatus = 200,
    profilePatchMessage = 'Не удалось обновить профиль.',
    order = emptyOrder,
    currentCycle = cycle,
    draftUnavailable = false,
    draftUnavailableMessage: draftUnavailableReason = null,
    orderItemPostStatus = 200,
    orderItemPostMessage = closedOrderingMessage,
    orderItemPatchStatus = 200,
    orderItemPatchMessage = closedOrderingMessage,
    submitOrderStatus = 200,
    submitOrderMessage = closedOrderingMessage,
    fridgeItems = [],
    fridgeHistory = [],
    fridgePatchStatus = 200,
    menuItems = [menuItem],
    menuCategories = [category],
    telegramLinkStatus = {
        linked: false,
        link_available: true,
        bot_link: 'https://t.me/lunch_demo_bot',
        bot_username: 'lunch_demo_bot',
    },
    telegramLinkTokenStatus = 201,
    telegramLinkTokenMessage = 'Не удалось подготовить ссылку Telegram.',
    telegramLinkTokenPending = false,
    telegramLoginConfig = {
        bot_username: 'lunch_demo_bot',
        bot_id: 7654321,
        login_available: true,
    },
    telegramLoginConfigStatus = 200,
    telegramLoginConfigMessage = 'Telegram login config unavailable.',
    telegramLoginConfigPending = false,
    telegramSiteLoginStatus = 200,
    telegramSiteLoginMessage = 'Не удалось войти через Telegram. Попробуйте ещё раз.',
    telegramSiteLoginUser = {
        ...user,
        telegram_id: '9001',
        name: 'Telegram User',
    },
    telegramSiteSessionTokenStatus = 200,
    telegramSiteSessionTokenMessage = 'Telegram login token not found.',
    telegramSiteSessionTokenValue = 'telegram-site-session-token',
    telegramWebAppAuthStatus = 200,
    telegramWebAppAuthMessage = 'Не удалось войти через Telegram. Попробуйте ещё раз.',
    telegramWebAppAuthToken = 'telegram-webapp-token',
    telegramWebAppAuthUser = {
        ...user,
        telegram_id: '9551',
        name: 'Telegram WebApp User',
    },
} = {}) => {
    let isAuthenticated = authenticated;
    let currentOrder = order;
    let currentFridgeItems = [...fridgeItems];
    let currentUser = { ...authenticatedUser };

    return vi.fn((input, options = {}) => {
        const path = String(input).replace('/api', '');
        const method = options.method ?? 'GET';

        if (path === '/auth/telegram-login/config' && method === 'GET') {
            if (telegramLoginConfigPending) {
                return new Promise(() => {});
            }

            if (telegramLoginConfigStatus >= 400) {
                return jsonResponse({ message: telegramLoginConfigMessage }, telegramLoginConfigStatus);
            }

            return jsonResponse({ data: telegramLoginConfig });
        }

        if (path === '/me') {
            return isAuthenticated
                ? jsonResponse({ data: currentUser })
                : jsonResponse({ message: 'Unauthenticated.' }, 401);
        }

        if (path === '/auth/telegram-login' && method === 'POST') {
            if (telegramSiteLoginStatus >= 400) {
                return jsonResponse({ message: telegramSiteLoginMessage }, telegramSiteLoginStatus);
            }

            isAuthenticated = true;
            currentUser = { ...telegramSiteLoginUser };

            return jsonResponse({
                data: {
                    token: 'telegram-site-token',
                    user: currentUser,
                },
            });
        }

        if (path === '/auth/telegram/token' && method === 'GET') {
            if (telegramSiteSessionTokenStatus >= 400) {
                return jsonResponse({ message: telegramSiteSessionTokenMessage }, telegramSiteSessionTokenStatus);
            }

            isAuthenticated = true;
            currentUser = { ...telegramSiteLoginUser };

            return jsonResponse({
                data: {
                    token: telegramSiteSessionTokenValue,
                },
            });
        }

        if (path === '/auth/telegram' && method === 'POST') {
            if (telegramWebAppAuthStatus >= 400) {
                return jsonResponse({ message: telegramWebAppAuthMessage }, telegramWebAppAuthStatus);
            }

            isAuthenticated = true;
            currentUser = { ...telegramWebAppAuthUser };

            return jsonResponse({
                data: {
                    token: telegramWebAppAuthToken,
                    user: currentUser,
                },
            });
        }

        if (path === '/auth/login' && method === 'POST') {
            isAuthenticated = true;

            return jsonResponse({
                data: {
                    token: 'web-login-token',
                    user: currentUser,
                },
            });
        }

        if (path === '/me/profile' && method === 'PATCH') {
            if (profilePatchStatus >= 400) {
                return jsonResponse({ message: profilePatchMessage }, profilePatchStatus);
            }

            const payload = options.body ? JSON.parse(options.body) : {};
            const normalizedFullName = typeof payload.full_name === 'string'
                ? payload.full_name.trim()
                : payload.full_name;

            currentUser = {
                ...currentUser,
                full_name: normalizedFullName ? normalizedFullName : null,
            };

            return jsonResponse({ data: currentUser });
        }

        if (path === '/current-cycle') {
            return jsonResponse({ data: currentCycle });
        }

        if (path === '/menu/categories') {
            return jsonResponse({ data: menuCategories });
        }

        if (path === '/menu/items') {
            return jsonResponse({ data: menuItems });
        }

        if (path === '/my-order' && method === 'GET') {
            return jsonResponse({
                data: {
                    cycle: currentCycle,
                    order: currentOrder,
                    draft_unavailable: draftUnavailable,
                    draft_unavailable_message: draftUnavailableReason,
                },
            });
        }

        if (path === '/my-order/items' && method === 'POST') {
            if (orderItemPostStatus >= 400) {
                return jsonResponse({ message: orderItemPostMessage }, orderItemPostStatus);
            }

            currentOrder = orderWithItem;
            return jsonResponse({ data: currentOrder });
        }

        if (path.startsWith('/my-order/items/') && method === 'PATCH') {
            if (orderItemPatchStatus >= 400) {
                return jsonResponse({ message: orderItemPatchMessage }, orderItemPatchStatus);
            }

            const payload = options.body ? JSON.parse(options.body) : {};
            const quantity = Number(payload.quantity ?? 1);
            currentOrder = {
                ...currentOrder,
                items: (currentOrder?.items ?? []).map((item) => (
                    String(item.id) === path.split('/').pop()
                        ? { ...item, quantity }
                        : item
                )),
            };

            return jsonResponse({ data: currentOrder });
        }

        if (path === '/my-order/submit' && method === 'POST') {
            if (submitOrderStatus >= 400) {
                return jsonResponse({ message: submitOrderMessage }, submitOrderStatus);
            }

            currentOrder = {
                ...currentOrder,
                status: 'submitted',
                can_submit: false,
            };

            return jsonResponse({ data: currentOrder });
        }

        if (path === '/my-order/reopen' && method === 'POST') {
            currentOrder = {
                ...currentOrder,
                status: 'draft',
                can_submit: true,
                can_reopen_for_editing: false,
            };

            return jsonResponse({ data: currentOrder });
        }

        if (path === '/my-fridge' && method === 'GET') {
            return jsonResponse({ data: currentFridgeItems });
        }

        if (path === '/my-fridge/history') {
            return jsonResponse({ data: fridgeHistory });
        }

        if (path === `/my-fridge/items/${fridgeItem.id}/eat-one` && method === 'PATCH') {
            if (fridgePatchStatus >= 400) {
                return jsonResponse({ message: 'Не удалось обновить холодильник.' }, fridgePatchStatus);
            }

            currentFridgeItems = currentFridgeItems.map((item) => (
                item.id === fridgeItem.id
                    ? { ...item, quantity_remaining: item.quantity_remaining - 1 }
                    : item
            ));

            return jsonResponse({ data: currentFridgeItems[0] });
        }

        if (path === `/my-fridge/items/${fridgeItem.id}/eat-all` && method === 'PATCH') {
            currentFridgeItems = currentFridgeItems.filter((item) => item.id !== fridgeItem.id);

            return jsonResponse({ data: { ...fridgeItem, quantity_remaining: 0, status: 'eaten' } });
        }

        if (path === `/my-fridge/items/${fridgeItem.id}/discard` && method === 'PATCH') {
            currentFridgeItems = currentFridgeItems.filter((item) => item.id !== fridgeItem.id);

            return jsonResponse({ data: { ...fridgeItem, quantity_remaining: 0, status: 'discarded' } });
        }

        if (path === '/auth/logout' && method === 'POST') {
            return jsonResponse({ data: { ok: true } });
        }

        if (path === '/telegram/link-status' && method === 'GET') {
            return jsonResponse({ data: telegramLinkStatus });
        }

        if (path === '/telegram/link-token' && method === 'POST') {
            if (telegramLinkTokenPending) {
                return new Promise(() => {});
            }

            if (telegramLinkTokenStatus >= 400) {
                return jsonResponse({ message: telegramLinkTokenMessage }, telegramLinkTokenStatus);
            }

            return jsonResponse({
                data: {
                    deep_link: 'https://t.me/lunch_demo_bot?start=link_test_token',
                    bot_link: 'https://t.me/lunch_demo_bot',
                    expires_at: '2026-05-25T12:10:00.000000Z',
                },
            }, telegramLinkTokenStatus);
        }

        return jsonResponse({ data: null });
    });
};

const mountApp = async (options = {}) => {
    const {
        authenticated = false,
        telegramInitData = '',
    } = options;

    sessionStorage.removeItem('lunch_mvp_require_full_name');

    if (authenticated) {
        if (!localStorage.getItem('lunch_mvp_token')) {
            localStorage.setItem('lunch_mvp_token', 'test-token');
        }
    }

    if (telegramInitData) {
        sessionStorage.setItem('lunch_mvp_require_full_name', '1');
    }

    window.Telegram = telegramInitData
        ? {
            WebApp: {
                initData: telegramInitData,
                ready: vi.fn(),
                expand: vi.fn(),
            },
        }
        : undefined;

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

const buttonByText = (text) => {
    return Array.from(document.querySelectorAll('button')).find((button) => button.textContent.includes(text));
};

const buttonByAriaLabel = (text) => {
    return Array.from(document.querySelectorAll('button')).find((button) => button.getAttribute('aria-label')?.includes(text));
};

const click = async (element) => {
    expect(element).toBeTruthy();
    element.click();
    await nextTick();
    await flushPromises();
};

const fillInput = async (input, value) => {
    expect(input).toBeTruthy();
    input.value = value;
    input.dispatchEvent(new Event('input'));
    await nextTick();
    await flushPromises();
};

const postedTo = (fetchMock, path) => {
    return fetchMock.mock.calls.some(([url, options = {}]) => String(url).includes(path) && options.method === 'POST');
};

const patchedTo = (fetchMock, path) => {
    return fetchMock.mock.calls.some(([url, options = {}]) => String(url).includes(path) && options.method === 'PATCH');
};

const requestCount = (fetchMock, path, method = 'GET') => {
    return fetchMock.mock.calls.filter(([url, options = {}]) => {
        return String(url).includes(path) && (options.method ?? 'GET') === method;
    }).length;
};

const installTelegramLoginMock = (result) => {
    const authMock = vi.fn((_options, callback) => {
        callback(typeof result === 'function' ? result() : result);
    });

    const telegram = window.Telegram ?? {};
    window.Telegram = {
        ...telegram,
        Login: {
            auth: authMock,
        },
    };

    return authMock;
};

describe('catalog auth UX', () => {
    it('shows loading surfaces before catalog requests settle without false empty states', async () => {
        global.fetch = vi.fn(() => new Promise(() => {}));

        const wrapper = mount(App, {
            attachTo: document.body,
            global: {
                plugins: [createPinia()],
            },
        });

        await nextTick();

        expect(document.querySelector('[data-testid="menu-status-strip"]')).toBeNull();
        const menuLoadingGrid = document.querySelector('.dishes-grid[aria-busy="true"]');
        const menuCardSkeleton = menuLoadingGrid?.querySelector('.menu-card [data-slot="skeleton"]');
        expect(menuCardSkeleton?.className).toContain('max-[430px]:h-[7.35rem]');
        expect(document.body.textContent).not.toContain('Недельный цикл не создан');
        expect(document.body.textContent).not.toContain('Ничего не найдено');
        expect(document.body.textContent).not.toContain('0 блюд');

        wrapper.unmount();
    });

    it('does not flash a guest login action while a saved session is resolving', async () => {
        localStorage.setItem('lunch_mvp_token', 'test-token');
        global.fetch = vi.fn(() => new Promise(() => {}));

        const wrapper = mount(App, {
            attachTo: document.body,
            global: {
                plugins: [createPinia()],
            },
        });

        await nextTick();

        expect(document.querySelector('[data-testid="header-auth-loading"]')).toBeTruthy();
        expect(buttonByText('Войти')).toBeFalsy();

        wrapper.unmount();
    });

    it('does not show stale order totals or read-only copy while protected data is loading', async () => {
        localStorage.setItem('lunch_mvp_token', 'test-token');
        global.fetch = vi.fn((input) => {
            const path = String(input).replace('/api', '');

            if (path === '/me') {
                return jsonResponse({ data: user });
            }

            return new Promise(() => {});
        });

        const wrapper = mount(App, {
            attachTo: document.body,
            global: {
                plugins: [createPinia()],
            },
        });

        await flushPromises();

        expect(document.querySelector('.catalog-order-panel')).toBeTruthy();
        expect(document.body.textContent).not.toContain('Прием заказов завершен.');
        expect(document.body.textContent).not.toContain('0 позиций');
        expect(document.body.textContent).not.toContain('0 ₽');
        expect(document.querySelector('.catalog-order-panel')?.textContent).not.toMatch(/Заказ\s*0/);
        expect(document.querySelector('.catalog-order-panel')?.textContent).not.toMatch(/Холодильник\s*0/);

        wrapper.unmount();
    });

    it('shows a true empty-menu state only after catalog loading completes', async () => {
        await mountApp({ menuItems: [], menuCategories: [] });

        expect(document.body.textContent).toContain('Меню на эту неделю пока пусто');
        expect(document.body.textContent).not.toContain('Ничего не найдено');
    });

    it('shows no-cycle guidance only after a loaded response has no cycle', async () => {
        await mountApp({ currentCycle: null, menuItems: [], menuCategories: [] });

        expect(document.querySelector('.week-status')).toBeNull();
        expect(document.querySelector('[data-testid="menu-status-strip"]')).toBeNull();
        expect(document.body.textContent).not.toContain('Недельный цикл не создан');
        expect(document.body.textContent).not.toContain('Меню появится после создания цикла администратором.');
    });

    it('does not render a global status strip under the header in the desktop main shell', async () => {
        await mountApp({
            authenticated: true,
            currentCycle: {
                ...cycle,
                status: 'closed',
                is_open_for_ordering: false,
                is_orderable: false,
                can_order: false,
                deadline_passed: true,
                availability_label: 'Заказ закрыт',
                availability_description: 'Администратор закрыл сбор заказов.',
            },
            order: {
                ...emptyOrder,
                status: 'draft',
            },
        });

        expect(document.querySelector('.week-status')).toBeNull();
        expect(document.querySelector('[data-testid="menu-status-strip"]')).toBeNull();
        expect(document.body.textContent).not.toContain('Приём заказов закрыт');
        expect(document.body.textContent).not.toContain('Администратор закрыл сбор заказов');
        expect(document.body.textContent?.toLowerCase()).not.toContain('черновик');
    });

    it('renders guest header with a wide neutral login action, muted search and no old guest copy', async () => {
        await mountApp();

        const loginButton = buttonByText('Войти');
        const searchInput = document.querySelector('#global-menu-search');
        const searchIcon = document.querySelector('[data-testid="global-search-icon"]');

        expect(loginButton).toBeTruthy();
        expect(loginButton?.className).toContain('min-w-[11.5rem]');
        expect(loginButton?.className).toContain('bg-[#f2f2f2]');
        expect(loginButton?.querySelector('.bg-white')).toBeNull();
        expect(searchInput?.getAttribute('placeholder')).toBe('Искать в меню');
        expect(searchInput?.className).toContain('placeholder:text-slate-400');
        expect(searchIcon?.getAttribute('class')).toContain('size-4');
        expect(searchIcon?.getAttribute('class')).toContain('text-slate-400');
        expect(document.body.textContent).not.toContain('Гость');
        expect(document.body.textContent).not.toContain('Вход в панели заказа');
    });

    it('renders catalog heading without legacy promo or menu-count copy', async () => {
        const fullMenu = Array.from({ length: 113 }, (_, index) => ({
            ...menuItem,
            id: 1000 + index,
            title: `Блюдо ${index + 1}`,
        }));

        await mountApp({
            menuItems: fullMenu,
            menuCategories: [category],
        });

        expect(document.querySelector('#menu-heading')?.textContent).toContain('Все блюда');
        expect(document.body.textContent).not.toContain('Меню недели');
        expect(document.body.textContent).not.toContain('Что нового');
        expect(document.body.textContent).not.toContain('доступно для заказа');
        expect(document.body.textContent).not.toContain('113 блюд в меню');
    });

    it('renders brand with uppercase N and a catalog-home button label', async () => {
        await mountApp();

        const brand = document.querySelector('[aria-label="NethammerEda"]');
        const homeButton = document.querySelector('button[aria-label="Вернуться в каталог"]');

        expect(brand).toBeTruthy();
        expect(brand?.textContent?.trim().startsWith('N')).toBe(true);
        expect(homeButton).toBeTruthy();
    });

    it('returns to the full catalog when the logo is clicked', async () => {
        await mountApp({
            menuItems: [menuItem, secondMenuItem],
            menuCategories: [category, secondCategory],
        });

        const searchInput = document.querySelector('#global-menu-search');
        await fillInput(searchInput, 'котлета');
        await click(buttonByText(secondCategory.name));
        expect(document.querySelector('#menu-heading')?.textContent).toContain(secondCategory.name);

        await click(document.querySelector('button[aria-label="Вернуться в каталог"]'));

        expect(searchInput.value).toBe('');
        expect(document.querySelector('#menu-heading')?.textContent).toContain('Все блюда');
        expect(document.body.textContent).toContain(menuItem.title);
        expect(document.body.textContent).toContain(secondMenuItem.title);
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

    it('restores authenticated state from persisted token on app boot', async () => {
        localStorage.setItem('lunch_mvp_token', 'persisted-token');
        const { fetchMock } = await mountApp({ authenticated: true });

        expect(requestCount(fetchMock, '/me')).toBeGreaterThan(0);
        expect(localStorage.getItem('lunch_mvp_token')).toBe('persisted-token');
        expect(buttonByText(user.name)).toBeTruthy();
    });

    it('clears invalid persisted token and falls back to guest state', async () => {
        localStorage.setItem('lunch_mvp_token', 'stale-token');
        const { fetchMock } = await mountApp({ authenticated: false });

        expect(requestCount(fetchMock, '/me')).toBeGreaterThan(0);
        expect(localStorage.getItem('lunch_mvp_token')).toBeNull();
        expect(buttonByText('Войти')).toBeTruthy();
        expect(document.querySelector('[data-testid="desktop-order-panel"]')?.textContent).toContain('Корзина пуста');
    });

    it('renders catalog even when telegram site-login endpoints fail', async () => {
        const { fetchMock } = await mountApp({
            telegramLoginConfigStatus: 503,
            telegramSiteLoginStatus: 422,
        });

        expect(document.querySelector('[data-testid="menu-status-strip"]')).toBeNull();
        expect(document.querySelector('#menu-heading')?.textContent).toContain('Все блюда');
        expect(document.querySelector('script[src*="telegram-widget.js"], script[src*="telegram-web-app.js"]')).toBeNull();
        expect(requestCount(fetchMock, '/auth/telegram-login/config')).toBe(0);
        expect(requestCount(fetchMock, '/auth/telegram-login')).toBe(0);
    });

    it('keeps email/password login modal and links telegram login to isolated page', async () => {
        const { fetchMock } = await mountApp();

        await click(buttonByText('Войти'));

        expect(document.querySelector('#auth-modal-email')).toBeTruthy();
        expect(document.querySelector('#auth-modal-password')).toBeTruthy();
        const telegramLink = document.querySelector('[data-testid="telegram-site-login-link"]');
        expect(telegramLink).toBeTruthy();
        expect(telegramLink?.getAttribute('href')).toBe('/auth/telegram');
        expect(document.querySelector('[data-testid="telegram-site-login-disabled"]')?.textContent)
            .toContain('Вход через Telegram временно недоступен');
        expect(requestCount(fetchMock, '/auth/telegram-login/config')).toBe(0);
        expect(requestCount(fetchMock, '/auth/telegram-login')).toBe(0);
    });

    it('submits email/password login and closes modal on success', async () => {
        const { fetchMock } = await mountApp();

        await click(buttonByText('Войти'));
        await fillInput(document.querySelector('#auth-modal-email'), 'user@lunch.local');
        await fillInput(document.querySelector('#auth-modal-password'), 'secret-123');
        await click(document.querySelector('button[type="submit"]'));

        expect(postedTo(fetchMock, '/auth/login')).toBe(true);
        expect(localStorage.getItem('lunch_mvp_token')).toBe('web-login-token');
        expect(sessionStorage.getItem('lunch_mvp_token')).toBeNull();
        expect(document.body.textContent).not.toContain('Вход в аккаунт');
        expect(buttonByText(user.name)).toBeTruthy();
    });

    it('persists token after site Telegram callback login', async () => {
        window.history.replaceState({}, '', '/?telegram_login=success');

        const telegramUser = {
            ...user,
            telegram_id: '9001',
            name: 'Telegram User',
        };

        const { fetchMock } = await mountApp({
            telegramSiteSessionTokenValue: 'telegram-site-session-token',
            telegramSiteLoginUser: telegramUser,
        });

        expect(requestCount(fetchMock, '/auth/telegram/token')).toBeGreaterThan(0);
        expect(localStorage.getItem('lunch_mvp_token')).toBe('telegram-site-session-token');
        expect(sessionStorage.getItem('lunch_mvp_token')).toBeNull();
        expect(buttonByText('Telegram User')).toBeTruthy();
        expect(window.location.search).not.toContain('telegram_login');
    });

    it('persists token after Telegram WebApp auth', async () => {
        const { fetchMock } = await mountApp({
            telegramInitData: 'telegram_init_payload',
            telegramWebAppAuthToken: 'telegram-webapp-token',
            telegramWebAppAuthUser: {
                ...user,
                telegram_id: '9551',
                name: 'Telegram WebApp User',
            },
        });

        expect(postedTo(fetchMock, '/auth/telegram')).toBe(true);
        expect(localStorage.getItem('lunch_mvp_token')).toBe('telegram-webapp-token');
        expect(sessionStorage.getItem('lunch_mvp_token')).toBeNull();
        expect(buttonByText('Telegram WebApp User')).toBeTruthy();
    });

    it('keeps user authenticated after remount with the same persisted token', async () => {
        const firstMount = await mountApp();

        await click(buttonByText('Войти'));
        await fillInput(document.querySelector('#auth-modal-email'), 'user@lunch.local');
        await fillInput(document.querySelector('#auth-modal-password'), 'secret-123');
        await click(document.querySelector('button[type="submit"]'));

        expect(localStorage.getItem('lunch_mvp_token')).toBe('web-login-token');
        firstMount.wrapper.unmount();

        const secondFetchMock = createFetchMock({ authenticated: true });
        global.fetch = secondFetchMock;
        const secondWrapper = mount(App, {
            attachTo: document.body,
            global: {
                plugins: [createPinia()],
            },
        });

        await flushPromises();
        await flushPromises();

        expect(requestCount(secondFetchMock, '/me')).toBeGreaterThan(0);
        expect(buttonByText(user.name)).toBeTruthy();

        secondWrapper.unmount();
    });

    it('shows a clean guest cart empty state without auth/status service copy', async () => {
        await mountApp();

        const panel = document.querySelector('[data-testid="desktop-order-panel"]');
        const panelButtons = Array.from(panel?.querySelectorAll('button') ?? []);
        const footer = panel?.querySelector('[data-testid="order-panel-footer"]');

        expect(panel).toBeTruthy();
        expect(panelButtons).toHaveLength(0);
        expect(footer?.querySelector('button')).toBeNull();
        expect(panel?.textContent).toContain('Корзина');
        expect(panel?.textContent).toContain('Корзина пуста');
        expect(panel?.textContent).toContain('Добавьте блюда из каталога.');
        expect(panel?.textContent).toContain('Итого');
        expect(panel?.textContent).toContain('0 ₽');
        expect(panel?.textContent).not.toContain('Войдите, чтобы заказать');
        expect(panel?.textContent).not.toContain('0 позиций');
        expect(panel?.textContent).not.toContain('закрыта');
        expect(panel?.textContent).not.toContain('Приём заказов закрыт');
    });

    it('opens login modal from guest product plus control instead of adding to cart', async () => {
        const { fetchMock } = await mountApp();

        await click(buttonByAriaLabel(`Добавить в заказ: ${menuItem.title}`));

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

    it('renders authenticated header with centered shell, large search, neutral profile and no old desktop nav', async () => {
        await mountApp({ authenticated: true });

        const header = document.querySelector('header');
        const headerInner = header?.querySelector('.header-inner');
        const searchInput = document.querySelector('#global-menu-search');
        const searchLabel = searchInput?.closest('label');
        const profileButton = buttonByText(user.name);

        expect(document.body.textContent).toContain(user.name);
        expect(buttonByText('Войти')).toBeFalsy();
        expect(headerInner).toBeTruthy();
        expect(headerInner?.className).toContain('header-inner');
        expect(header?.querySelector('[aria-label="Открыть раздел: Каталог"]')).toBeNull();
        expect(header?.querySelector('[aria-label="Открыть раздел: Холодильник"]')).toBeNull();
        expect(header?.querySelector('[aria-label="Открыть раздел: История"]')).toBeNull();
        expect(header?.textContent).not.toContain('Холодильник · 13');
        expect(searchLabel?.className).toContain('md:max-w-[66rem]');
        expect(searchInput).toBeTruthy();
        expect(searchInput?.getAttribute('placeholder')).toBe('Искать в меню');
        expect(searchInput?.className).toContain('h-12');
        expect(searchInput?.className).toContain('rounded-full');
        expect(searchInput?.className).toContain('bg-[#f2f2f2]');
        expect(searchInput?.className).toContain('placeholder:text-slate-400');
        expect(profileButton?.className).toContain('h-12');
        expect(profileButton?.className).toContain('min-w-[13.5rem]');
        expect(profileButton?.className).toContain('bg-[#f2f2f2]');
        expect(profileButton?.querySelector('.bg-white')).toBeNull();
        expect(document.querySelector('.catalog-order-panel')).toBeTruthy();
        expect(document.body.textContent).toContain('Корзина');
        expect(document.body.textContent).not.toContain('Позиций:');
    });

    it('does not render old desktop nav buttons in the header', async () => {
        const fridgeItems = Array.from({ length: 13 }, (_, index) => ({
            ...fridgeItem,
            id: 400 + index,
            title_snapshot: `Позиция ${index + 1}`,
            quantity_remaining: 1,
        }));

        await mountApp({
            authenticated: true,
            fridgeItems,
        });

        const header = document.querySelector('header');

        expect(header?.querySelector('[aria-label="Открыть раздел: Каталог"]')).toBeNull();
        expect(header?.querySelector('[aria-label="Открыть раздел: Холодильник"]')).toBeNull();
        expect(header?.querySelector('[aria-label="Открыть раздел: История"]')).toBeNull();
        expect(header?.textContent).not.toContain('Холодильник · 13');
        expect(document.querySelector('[aria-label="Открыть раздел: Холодильник"]')).toBeTruthy();
    });

    it('renders centered desktop shell with category rail, catalog shelf and high cart panel', async () => {
        await mountApp({
            authenticated: true,
            order: orderWithItem,
        });

        const mainShell = document.querySelector('main.page-shell.app-main-shell');
        const catalogScroll = document.querySelector('[data-testid="catalog-scroll-panel"]');
        const cartPanel = document.querySelector('[data-testid="desktop-order-panel"]');

        expect(mainShell).toBeTruthy();
        expect(document.querySelector('[data-testid="menu-category-rail"]')).toBeTruthy();
        expect(document.getElementById('menu-heading')?.textContent).toContain('Все блюда');
        expect(catalogScroll?.className).toContain('menu-shell__catalog-scroll');
        expect(catalogScroll?.className).toContain('scrollbar-none');
        expect(document.querySelector('[data-testid="catalog-shelf-panel"]')).toBeTruthy();
        expect(document.querySelector('[data-testid="menu-status-strip"]')).toBeNull();
        expect(cartPanel).toBeTruthy();
        expect(cartPanel?.className).toContain('xl:sticky');
        expect(cartPanel?.className).toContain('xl:top-[5.75rem]');
    });

    it('opens profile modal with account actions', async () => {
        await mountApp({ authenticated: true });

        await click(buttonByText(user.name));

        expect(document.body.textContent).toContain(user.name);
        expect(document.body.textContent).toContain(user.email);
        expect(document.body.textContent).toContain('Избранное');
        expect(document.body.textContent).toContain('Мой заказ');
        expect(document.body.textContent).toContain('Холодильник');
        expect(document.body.textContent).toContain('История');
        expect(document.body.textContent).toContain('Укажите ФИО в формате: Фамилия и инициалы. Например: Иванов И.И.');
        expect(document.body.textContent).toContain('Telegram-бот');
        expect(document.body.textContent).toContain('Привяжите Telegram, чтобы получать уведомления о заказах.');
        expect(document.body.textContent).toContain('Привязка занимает несколько секунд.');
        expect(document.body.textContent).toContain('Привязать Telegram');
        expect(document.body.textContent).not.toContain('/order, /fridge, /history');
        expect(document.body.textContent).not.toContain('Настройки');
        expect(buttonByText('Выйти')).toBeTruthy();
    });

    it('prefers full_name over name in header and profile surfaces', async () => {
        const account = {
            ...user,
            name: 'Administrator',
            full_name: 'Иванов И.И.',
        };

        await mountApp({
            authenticated: true,
            authenticatedUser: account,
        });

        expect(buttonByText('Иванов И.И.')).toBeTruthy();
        expect(buttonByText('Administrator')).toBeFalsy();

        await click(buttonByText('Иванов И.И.'));
        expect(document.querySelector('[data-testid="profile-name"]')?.textContent).toContain('Иванов И.И.');
    });

    it('updates full_name via profile API and keeps updated value in auth state', async () => {
        const account = {
            ...user,
            name: 'Administrator',
            full_name: null,
        };
        const { fetchMock } = await mountApp({
            authenticated: true,
            authenticatedUser: account,
        });

        await click(buttonByText('Administrator'));

        const fullNameInput = document.querySelector('[data-testid="profile-full-name-input"]');
        await fillInput(fullNameInput, 'Иванов И.И.');
        await click(document.querySelector('[data-testid="profile-save-full-name"]'));

        expect(patchedTo(fetchMock, '/me/profile')).toBe(true);
        expect(document.body.textContent).toContain('Профиль обновлен.');
        expect(document.querySelector('[data-testid="profile-name"]')?.textContent).toContain('Иванов И.И.');
        expect(buttonByText('Иванов И.И.')).toBeTruthy();
    });

    it('shows required full-name modal for telegram sessions with empty full_name', async () => {
        await mountApp({
            authenticated: true,
            authenticatedUser: {
                ...user,
                name: 'Telegram User',
                full_name: null,
            },
            telegramInitData: 'telegram_init_payload',
        });

        expect(document.querySelector('[data-testid="required-full-name-modal"]')).toBeTruthy();
        expect(document.querySelector('[data-testid="required-full-name-title"]')?.textContent).toContain('Введите ФИО');
        expect(document.querySelector('[data-testid="required-full-name-example"]')?.textContent).toContain('Например: Иванов И.И.');
        expect(document.querySelector('[data-testid="required-full-name-input"]')?.getAttribute('placeholder')).toBe('Иванов И.И.');
        expect(document.querySelector('[data-testid="required-full-name-input"]')?.className).toContain('text-center');
    });

    it('does not show required full-name modal when telegram user already has full_name', async () => {
        await mountApp({
            authenticated: true,
            authenticatedUser: {
                ...user,
                full_name: 'Иванов Иван',
            },
            telegramInitData: 'telegram_init_payload',
        });

        expect(document.querySelector('[data-testid="required-full-name-modal"]')).toBeNull();
    });

    it('keeps required full-name modal open until a valid full_name is saved', async () => {
        const { fetchMock } = await mountApp({
            authenticated: true,
            authenticatedUser: {
                ...user,
                name: 'Telegram User',
                full_name: null,
            },
            telegramInitData: 'telegram_init_payload',
        });

        const requiredInput = document.querySelector('[data-testid="required-full-name-input"]');
        await fillInput(requiredInput, 'Иванов');
        await click(document.querySelector('[data-testid="required-full-name-save"]'));

        expect(document.querySelector('[data-testid="required-full-name-error"]')?.textContent).toContain('Укажите минимум имя и фамилию.');
        expect(document.querySelector('[data-testid="required-full-name-modal"]')).toBeTruthy();

        await fillInput(requiredInput, 'Иванов Иван');
        await click(document.querySelector('[data-testid="required-full-name-save"]'));

        expect(patchedTo(fetchMock, '/me/profile')).toBe(true);
        expect(document.querySelector('[data-testid="required-full-name-modal"]')).toBeNull();
    });

    it('shows api error in required full-name modal when saving fails', async () => {
        await mountApp({
            authenticated: true,
            authenticatedUser: {
                ...user,
                name: 'Telegram User',
                full_name: null,
            },
            telegramInitData: 'telegram_init_payload',
            profilePatchStatus: 422,
            profilePatchMessage: 'Некорректное ФИО.',
        });

        const requiredInput = document.querySelector('[data-testid="required-full-name-input"]');
        await fillInput(requiredInput, 'Иванов Иван');
        await click(document.querySelector('[data-testid="required-full-name-save"]'));

        expect(document.querySelector('[data-testid="required-full-name-error"]')?.textContent).toContain('Некорректное ФИО.');
        expect(document.querySelector('[data-testid="required-full-name-modal"]')).toBeTruthy();
    });

    it('shows telegram linked state with open bot action', async () => {
        await mountApp({
            authenticated: true,
            authenticatedUser: { ...user, telegram_id: '9551' },
            telegramLinkStatus: {
                linked: true,
                link_available: true,
                bot_link: 'https://t.me/lunch_demo_bot',
                bot_username: 'lunch_demo_bot',
            },
        });

        await click(buttonByText(user.name));

        expect(document.querySelector('[data-testid="profile-telegram-linked-text"]')?.textContent).toContain('Подключён.');
        expect(document.querySelector('[data-testid="profile-telegram-linked"]')?.textContent).toContain('Подключён');
        expect(document.querySelector('[data-testid="profile-telegram-open-bot"]')).toBeTruthy();
        expect(document.querySelector('[data-testid="profile-telegram-open-bot"]')?.textContent).toContain('Открыть Telegram');
        expect(document.querySelector('[data-testid="profile-telegram-link"]')).toBeNull();
        expect(document.querySelector('[data-testid="profile-telegram-identity"]')?.textContent).toContain('9551');
    });

    it('shows compact unavailable hint when telegram linking is disabled', async () => {
        await mountApp({
            authenticated: true,
            telegramLinkStatus: {
                linked: false,
                link_available: false,
                bot_link: null,
                bot_username: null,
            },
        });

        await click(buttonByText(user.name));

        expect(document.querySelector('[data-testid="profile-telegram-link"]')).toBeNull();
        expect(document.querySelector('[data-testid="profile-telegram-unavailable"]')?.textContent).toContain('Привязка временно недоступна.');
        expect(document.querySelector('[data-testid="profile-telegram-unavailable-hint"]')?.textContent).toContain('Обратитесь к администратору.');
    });

    it('shows telegram link loading state while token is being created', async () => {
        await mountApp({
            authenticated: true,
            telegramLinkTokenPending: true,
        });

        await click(buttonByText(user.name));
        await click(document.querySelector('[data-testid="profile-telegram-link"]'));

        const button = document.querySelector('[data-testid="profile-telegram-link"]');
        expect(button?.textContent).toContain('Создаём ссылку...');
        expect(button?.disabled).toBe(true);
    });

    it('shows telegram link error inside profile block when token creation fails', async () => {
        await mountApp({
            authenticated: true,
            telegramLinkTokenStatus: 422,
            telegramLinkTokenMessage: 'Сервис Telegram временно недоступен.',
        });

        await click(buttonByText(user.name));
        await click(document.querySelector('[data-testid="profile-telegram-link"]'));

        expect(document.querySelector('[data-testid="profile-telegram-error"]')?.textContent).toContain('Не удалось создать ссылку. Попробуйте ещё раз.');
    });

    it('handles a long user name in the header and profile surface', async () => {
        const longName = 'Очень длинное имя сотрудника отдела разработки корпоративных сервисов';

        await mountApp({
            authenticated: true,
            authenticatedUser: { ...user, name: longName },
        });

        const profileTrigger = document.querySelector('[aria-label^="Открыть профиль:"]');
        expect(profileTrigger?.getAttribute('title')).toBe(longName);

        await click(profileTrigger);
        expect(document.querySelector('[data-testid="profile-name"]')?.textContent).toContain(longName);
    });

    it('opens favorites from profile and explains an empty favorite collection', async () => {
        await mountApp({ authenticated: true });

        await click(buttonByText(user.name));
        await click(document.querySelector('[data-testid="profile-favorites-action"]'));

        expect(document.querySelector('[data-testid="menu-favorites-chip"]')).toBeNull();
        expect(document.body.textContent).toContain('В избранном пока ничего нет.');
        expect(document.body.textContent).toContain('Нажимайте сердечко на блюдах, чтобы сохранить их здесь.');
    });

    it('opens order, fridge and history sheets from profile actions', async () => {
        await mountApp({
            authenticated: true,
            fridgeHistory: [fridgeHistoryItem],
        });

        await click(buttonByText(user.name));
        await click(document.querySelector('[data-testid="profile-order-action"]'));
        expect(document.querySelector('[data-testid="mobile-order-panel"]')).toBeTruthy();

        await click(document.querySelector('[aria-label="Закрыть мой заказ"]'));
        await click(document.querySelector('[aria-label^="Открыть профиль:"]'));
        await click(document.querySelector('[data-testid="profile-fridge-action"]'));
        expect(document.querySelector('[data-testid="mobile-fridge-panel"]')).toBeTruthy();

        await click(document.querySelector('[aria-label="Закрыть холодильник"]'));
        await click(document.querySelector('[aria-label^="Открыть профиль:"]'));
        await click(document.querySelector('[data-testid="profile-history-action"]'));
        expect(document.querySelector('[data-testid="mobile-history-panel"]')).toBeTruthy();
        expect(document.body.textContent).toContain(fridgeHistoryItem.title_snapshot);
    });

    it('logs out from profile and returns catalog to guest state', async () => {
        const { fetchMock } = await mountApp({ authenticated: true });

        await click(buttonByText(user.name));
        await click(buttonByText('Выйти'));

        expect(postedTo(fetchMock, '/auth/logout')).toBe(true);
        expect(localStorage.getItem('lunch_mvp_token')).toBeNull();
        expect(sessionStorage.getItem('lunch_mvp_token')).toBeNull();
        expect(document.body.textContent).not.toContain(user.name);
        expect(buttonByText('Войти')).toBeTruthy();
        expect(document.querySelector('.catalog-order-panel')).toBeTruthy();
        expect(document.querySelector('.catalog-order-panel')?.textContent).toContain('Корзина пуста');
    });

    it('lets authenticated users add items to the order', async () => {
        const { fetchMock } = await mountApp({ authenticated: true });

        await click(buttonByAriaLabel(`Добавить в заказ: ${menuItem.title}`));

        expect(postedTo(fetchMock, '/my-order/items')).toBe(true);
        expect(document.querySelector('[data-testid="menu-item-quantity-overlay"]')?.textContent).toContain('1');
    });

    it('filters dishes by search and category selection', async () => {
        await mountApp({
            menuItems: [menuItem, secondMenuItem],
            menuCategories: [category, secondCategory],
        });

        expect(document.body.textContent).toContain(menuItem.title);
        expect(document.body.textContent).toContain(secondMenuItem.title);
        expect(document.querySelectorAll('[data-testid="menu-category-section"]')).toHaveLength(2);

        const searchInput = document.querySelector('#menu-search');
        searchInput.value = 'котлета';
        searchInput.dispatchEvent(new Event('input', { bubbles: true }));
        await nextTick();

        expect(document.body.textContent).not.toContain(menuItem.title);
        expect(document.body.textContent).toContain(secondMenuItem.title);
        expect(document.querySelectorAll('[data-testid="menu-category-section"]')).toHaveLength(1);

        const filteredCategoryHeading = document.querySelector('[data-testid="menu-category-heading"]');
        expect(filteredCategoryHeading?.textContent ?? '').toContain(secondCategory.name);

        searchInput.value = '';
        searchInput.dispatchEvent(new Event('input', { bubbles: true }));
        await click(buttonByText(secondCategory.name));

        expect(document.body.textContent).not.toContain(menuItem.title);
        expect(document.body.textContent).toContain(secondMenuItem.title);

        const selectedCategorySummary = document.querySelector('[data-testid="menu-selected-category-summary"]');
        expect(selectedCategorySummary?.textContent ?? '').toContain(secondCategory.name);
    });

    it('keeps favorites outside normal category list and preserves wrapping-safe category chips', async () => {
        await mountApp({
            authenticated: true,
            menuItems: [menuItem, secondMenuItem],
            menuCategories: [category, secondCategory],
        });

        const row = document.querySelector('[data-testid="category-chip-row"]');
        const favoriteChip = document.querySelector('[data-testid="menu-favorites-chip"]');

        expect(row).toBeTruthy();
        expect(row?.className).toContain('flex-wrap');
        expect(row?.className).toContain('max-w-full');
        expect(row?.className).toContain('min-w-0');
        expect(row?.className).not.toContain('w-max');
        expect(row?.className).not.toContain('min-w-full');
        expect(row?.className).toContain('xl:flex-nowrap');

        expect(favoriteChip).toBeNull();
        expect(row?.textContent).not.toContain('Избранное');
    });

    it('filters the catalog to locally selected favorites', async () => {
        await mountApp({
            authenticated: true,
            menuItems: [menuItem, secondMenuItem],
            menuCategories: [category, secondCategory],
        });

        await click(document.querySelector(`[aria-label="Добавить в избранное: ${menuItem.title}"]`));
        await click(buttonByText(user.name));
        await click(document.querySelector('[data-testid="profile-favorites-action"]'));

        expect(document.body.textContent).toContain(menuItem.title);
        expect(document.body.textContent).not.toContain(secondMenuItem.title);
    });

    it('shows the catalog as orderable when the cycle can accept orders', async () => {
        await mountApp({ authenticated: true });

        const plusButton = buttonByAriaLabel(`Добавить в заказ: ${menuItem.title}`);
        const stepper = document.querySelector('[data-testid="menu-item-price-stepper"]');

        expect(document.querySelector('[data-testid="menu-status-strip"]')).toBeNull();
        expect(plusButton?.disabled).toBe(false);
        expect(stepper?.textContent).toContain('250');
    });

    it('shows deadline passed separately from a closed cycle', async () => {
        await mountApp({
            authenticated: true,
            currentCycle: {
                ...cycle,
                is_open_for_ordering: false,
                is_orderable: false,
                can_order: false,
                deadline_passed: true,
                availability_label: 'Дедлайн прошел',
                availability_description: 'Прием заказов завершен.',
            },
        });

        const plusButton = buttonByAriaLabel(`Добавить в заказ: ${menuItem.title}`);
        const stepper = document.querySelector('[data-testid="menu-item-price-stepper"]');

        expect(document.body.textContent).not.toContain('Приём заказов закрыт');
        expect(document.body.textContent).not.toContain('Заказ закрыт');
        expect(plusButton?.disabled).toBe(true);
        expect(stepper?.className).toContain('bg-blue-50/60');
    });

    it('shows delivery guidance when a cycle is delivered', async () => {
        await mountApp({
            authenticated: true,
            currentCycle: {
                ...cycle,
                status: 'delivered',
                is_open_for_ordering: false,
                can_order: false,
                availability_label: 'Доставлен',
                availability_description: 'Доставка отмечена, блюда попали в холодильники.',
            },
        });

        expect(document.querySelector('.week-status')).toBeNull();
        expect(document.body.textContent).not.toContain('Приём заказов закрыт');
        expect(document.body.textContent).not.toContain('Проверьте холодильник.');
    });

    it('shows a reopen action for a submitted order before the deadline', async () => {
        await mountApp({
            authenticated: true,
            menuItems: [menuItem, secondMenuItem],
            menuCategories: [category, secondCategory],
            order: submittedOrder,
        });

        const selectedPlus = buttonByAriaLabel(`Увеличить количество: ${menuItem.title}`);
        const unselectedPlus = buttonByAriaLabel(`Добавить в заказ: ${secondMenuItem.title}`);

        expect(buttonByText('Редактировать заказ')).toBeTruthy();
        expect(document.body.textContent).not.toContain('Приём заказов открыт · до');
        expect(selectedPlus?.disabled).toBe(true);
        expect(unselectedPlus?.disabled).toBe(true);
    });

    it('uses API deadline display fields without timezone conversion shift', async () => {
        await mountApp({
            authenticated: true,
            order: submittedOrder,
            currentCycle: {
                ...cycle,
                closes_at: '2026-05-29T12:00:00.000000Z',
                deadline_date: '29.05',
                deadline_time: '12:00',
                deadline_display: '29.05, 12:00',
                deadline_display_full: '29.05.2026, 12:00',
            },
        });

        const pageText = document.body.textContent ?? '';
        expect(document.querySelector('[data-testid="menu-status-strip"]')).toBeNull();
        expect(pageText).not.toContain('29.05, 17:00');
    });

    it('refreshes ordering state when the deadline passes on an already open page', async () => {
        vi.useFakeTimers();
        vi.setSystemTime(new Date('2026-05-22T11:59:55.000Z'));
        localStorage.setItem('lunch_mvp_token', 'test-token');

        const openCycle = {
            ...cycle,
            closes_at: '2026-05-22T12:00:00.000000Z',
            deadline_date: '22.05',
            deadline_time: '12:00',
            deadline_display: '22.05, 12:00',
            deadline_display_full: '22.05.2026, 12:00',
            status: 'open',
            is_open_for_ordering: true,
            is_orderable: true,
            can_order: true,
            deadline_passed: false,
        };
        const closedCycle = {
            ...openCycle,
            status: 'closed',
            is_open_for_ordering: false,
            is_orderable: false,
            can_order: false,
            deadline_passed: true,
            availability_label: 'Заказ закрыт',
            availability_description: 'Администратор закрыл сбор заказов.',
        };

        let currentCycleRequestCount = 0;
        global.fetch = vi.fn((input, options = {}) => {
            const path = String(input).replace('/api', '');
            const method = options.method ?? 'GET';

            if (path === '/me') {
                return jsonResponse({ data: user });
            }

            if (path === '/current-cycle') {
                currentCycleRequestCount += 1;

                return jsonResponse({
                    data: currentCycleRequestCount === 1 ? openCycle : closedCycle,
                });
            }

            if (path === '/menu/categories') {
                return jsonResponse({ data: [category] });
            }

            if (path === '/menu/items') {
                return jsonResponse({ data: [menuItem] });
            }

            if (path === '/my-order' && method === 'GET') {
                const activeCycle = currentCycleRequestCount <= 1 ? openCycle : closedCycle;

                return jsonResponse({
                    data: {
                        cycle: activeCycle,
                        order: orderWithItem,
                    },
                });
            }

            if (path === '/my-fridge' && method === 'GET') {
                return jsonResponse({ data: [] });
            }

            if (path === '/my-fridge/history') {
                return jsonResponse({ data: [] });
            }

            return jsonResponse({ data: null });
        });

        const wrapper = mount(App, {
            attachTo: document.body,
            global: {
                plugins: [createPinia()],
            },
        });

        await flushPromises();
        await flushPromises();

        expect(document.querySelector(`[aria-label="Увеличить количество: ${menuItem.title}"]`)).toBeTruthy();

        await vi.advanceTimersByTimeAsync(5100);
        await flushPromises();
        await flushPromises();

        const addPlus = buttonByAriaLabel(`Добавить в заказ: ${menuItem.title}`);

        expect(currentCycleRequestCount).toBeGreaterThan(1);
        expect(document.body.textContent).not.toContain('Приём заказов закрыт');
        expect(document.body.textContent).not.toContain('Можно редактировать до');
        expect(addPlus?.disabled).toBe(true);

        wrapper.unmount();
        vi.useRealTimers();
    });

    it('hides stale closed-cycle draft from cart and shows draft-unavailable message', async () => {
        await mountApp({
            authenticated: true,
            menuItems: [menuItem],
            menuCategories: [category],
            currentCycle: {
                ...cycle,
                status: 'closed',
                is_open_for_ordering: false,
                is_orderable: false,
                can_order: false,
                deadline_passed: true,
                availability_label: 'Заказ закрыт',
                availability_description: 'Администратор закрыл сбор заказов.',
            },
            order: {
                ...orderWithItem,
                status: 'draft',
            },
            draftUnavailable: true,
            draftUnavailableMessage,
        });

        expect(document.querySelectorAll('.catalog-order-panel article').length).toBe(0);
        expect(document.body.textContent).not.toContain(closedOrderingInfoMessage);
        expect(document.body.textContent).not.toContain('черновик');
    });

    it('clears active order when mutation returns closed-cycle response', async () => {
        localStorage.setItem('lunch_mvp_token', 'test-token');

        const openCycle = {
            ...cycle,
            status: 'open',
            is_open_for_ordering: true,
            is_orderable: true,
            can_order: true,
            deadline_passed: false,
        };
        const closedCycle = {
            ...openCycle,
            status: 'closed',
            is_open_for_ordering: false,
            is_orderable: false,
            can_order: false,
            deadline_passed: true,
            availability_label: 'Заказ закрыт',
            availability_description: 'Администратор закрыл сбор заказов.',
        };

        let closedStateApplied = false;
        global.fetch = vi.fn((input, options = {}) => {
            const path = String(input).replace('/api', '');
            const method = options.method ?? 'GET';

            if (path === '/me') {
                return jsonResponse({ data: user });
            }

            if (path === '/current-cycle') {
                return jsonResponse({ data: closedStateApplied ? closedCycle : openCycle });
            }

            if (path === '/menu/categories') {
                return jsonResponse({ data: [category, secondCategory] });
            }

            if (path === '/menu/items') {
                return jsonResponse({ data: [menuItem, secondMenuItem] });
            }

            if (path === '/my-order' && method === 'GET') {
                if (!closedStateApplied) {
                    return jsonResponse({
                        data: {
                            cycle: openCycle,
                            order: orderWithItem,
                            draft_unavailable: false,
                            draft_unavailable_message: null,
                        },
                    });
                }

                return jsonResponse({
                    data: {
                        cycle: closedCycle,
                        order: null,
                        draft_unavailable: true,
                        draft_unavailable_message: draftUnavailableMessage,
                    },
                });
            }

            if (path === '/my-order/items' && method === 'POST') {
                closedStateApplied = true;
                return jsonResponse({ message: closedOrderingMessage }, 422);
            }

            if (path === '/my-fridge' && method === 'GET') {
                return jsonResponse({ data: [] });
            }

            if (path === '/my-fridge/history') {
                return jsonResponse({ data: [] });
            }

            return jsonResponse({ data: null });
        });

        const wrapper = mount(App, {
            attachTo: document.body,
            global: {
                plugins: [createPinia()],
            },
        });

        await flushPromises();
        await flushPromises();

        await click(buttonByAriaLabel(`Добавить в заказ: ${secondMenuItem.title}`));

        expect(document.body.textContent).toContain(closedOrderingCartClearedMessage);
        expect(document.querySelectorAll('.catalog-order-panel article').length).toBe(0);

        wrapper.unmount();
    });

    it('does not show editable-until copy when API returns a closed cycle', async () => {
        await mountApp({
            authenticated: true,
            menuItems: [menuItem],
            menuCategories: [category],
            currentCycle: {
                ...cycle,
                status: 'closed',
                is_open_for_ordering: false,
                is_orderable: false,
                can_order: false,
                deadline_passed: true,
                availability_label: 'Заказ закрыт',
                availability_description: 'Администратор закрыл сбор заказов.',
            },
            order: {
                ...submittedOrder,
                can_reopen_for_editing: false,
            },
        });

        const pageText = document.body.textContent ?? '';
        const selectedPlus = buttonByAriaLabel(`Увеличить количество: ${menuItem.title}`);

        expect(pageText).not.toContain('Приём заказов закрыт');
        expect(pageText).not.toContain('Можно редактировать до');
        expect(buttonByText('Редактировать заказ')).toBeFalsy();
        expect(selectedPlus?.disabled).toBe(true);
    });

    it('reopens a submitted order and enables order controls again', async () => {
        const { fetchMock } = await mountApp({
            authenticated: true,
            menuItems: [menuItem, secondMenuItem],
            menuCategories: [category, secondCategory],
            order: submittedOrder,
        });

        await click(buttonByText('Редактировать заказ'));

        expect(postedTo(fetchMock, '/my-order/reopen')).toBe(true);
        expect(buttonByText('Редактировать заказ')).toBeFalsy();
        expect(buttonByAriaLabel(`Увеличить количество: ${menuItem.title}`)?.disabled).toBe(false);
        expect(buttonByText('Оформить заказ')?.disabled).toBe(false);
        expect(buttonByAriaLabel(`Добавить в заказ: ${secondMenuItem.title}`)?.disabled).toBe(false);
    });

    it('does not show reopen action after the deadline and keeps submitted controls disabled', async () => {
        await mountApp({
            authenticated: true,
            menuItems: [menuItem, secondMenuItem],
            menuCategories: [category, secondCategory],
            currentCycle: {
                ...cycle,
                is_open_for_ordering: false,
                is_orderable: false,
                can_order: false,
                deadline_passed: true,
                availability_label: 'Дедлайн прошел',
                availability_description: 'Прием заказов завершен.',
            },
            order: {
                ...submittedOrder,
                can_reopen_for_editing: false,
            },
        });

        expect(buttonByText('Редактировать заказ')).toBeFalsy();
        expect(document.body.textContent).not.toContain('Приём заказов закрыт');
        expect(buttonByAriaLabel(`Увеличить количество: ${menuItem.title}`)?.disabled).toBe(true);
        expect(buttonByAriaLabel(`Добавить в заказ: ${secondMenuItem.title}`)?.disabled).toBe(true);

        await click(document.querySelector('[aria-label="Открыть раздел: Корзина"]'));
        const mobileOrderText = document.querySelector('[data-testid="mobile-order-panel"]')?.textContent ?? '';
        expect(mobileOrderText).not.toContain('Приём заказов закрыт');
        expect(mobileOrderText).not.toContain('отправьте заказ до дедлайна');
    });

    it('keeps a submitted order visually read-only even while the cycle remains open', async () => {
        await mountApp({
            authenticated: true,
            menuItems: [menuItem, secondMenuItem],
            menuCategories: [category, secondCategory],
            order: {
                ...orderWithItem,
                status: 'submitted',
            },
        });

        expect(document.body.textContent).not.toContain('Приём заказов открыт');
        expect(buttonByAriaLabel(`Увеличить количество: ${menuItem.title}`)?.disabled).toBe(true);
        expect(buttonByAriaLabel(`Добавить в заказ: ${secondMenuItem.title}`)?.disabled).toBe(true);

        await click(document.querySelector('[aria-label="Открыть раздел: Корзина"]'));
        const mobileOrderText = document.querySelector('[data-testid="mobile-order-panel"]')?.textContent ?? '';
        expect(mobileOrderText).not.toContain('Приём заказов открыт');
        expect(mobileOrderText).not.toContain('отправьте заказ до дедлайна');
    });

    it('opens mobile order, fridge and profile from bottom navigation', async () => {
        await mountApp({ authenticated: true });

        await click(document.querySelector('[aria-label="Открыть раздел: Корзина"]'));
        expect(document.querySelector('[data-testid="mobile-order-panel"]')).toBeTruthy();

        await click(document.querySelector('[aria-label="Закрыть мой заказ"]'));
        await click(document.querySelector('[aria-label="Открыть раздел: Холодильник"]'));
        expect(document.querySelector('[data-testid="mobile-fridge-panel"]')).toBeTruthy();

        await click(document.querySelector('[aria-label="Закрыть холодильник"]'));
        await click(document.querySelector('[aria-label="Открыть раздел: Профиль"]'));
        expect(document.querySelector('[data-testid="profile-name"]')).toBeTruthy();
    });

    it('closes an open mobile sheet after switching to a desktop viewport', async () => {
        let desktop = false;
        let listener = null;

        vi.spyOn(window, 'matchMedia').mockImplementation(() => ({
            get matches() {
                return desktop;
            },
            addEventListener: (event, callback) => {
                if (event === 'change') {
                    listener = callback;
                }
            },
            removeEventListener: vi.fn(),
            addListener: vi.fn(),
            removeListener: vi.fn(),
            dispatchEvent: vi.fn(),
        }));

        await mountApp({ authenticated: true });
        await click(document.querySelector('[aria-label="Открыть раздел: Корзина"]'));
        expect(document.querySelector('[data-testid="mobile-order-panel"]')).toBeTruthy();

        desktop = true;
        listener?.({ matches: true });
        await nextTick();

        expect(document.querySelector('[data-testid="mobile-order-panel"]')?.dataset.state).toBe('closed');
    });

    it('falls back to a placeholder if a menu image cannot be loaded', async () => {
        await mountApp({
            menuItems: [{ ...menuItem, image_url: 'https://example.com/broken-image.jpg' }],
        });

        const image = document.querySelector(`img[alt="${menuItem.title}"]`);
        expect(image).toBeTruthy();
        await image.dispatchEvent(new Event('error'));
        await nextTick();

        expect(document.body.textContent).toContain('Фото скоро');
    });

    it('renders menu cards from image_display_url before supplier image_url', async () => {
        await mountApp({
            menuItems: [{
                ...menuItem,
                image_display_url: '/storage/menu-items/manual/11/soup.png',
                image_url: 'https://example.com/supplier-soup.png',
            }],
        });

        const image = document.querySelector(`img[alt="${menuItem.title}"]`);

        expect(image?.getAttribute('src')).toBe('/storage/menu-items/manual/11/soup.png');
    });

    it('keeps menu photography in a dedicated calm media area', async () => {
        await mountApp({
            menuItems: [{
                ...menuItem,
                image_display_url: '/storage/menu-items/manual/11/soup.png',
            }],
        });

        const imageArea = document.querySelector('[data-testid="menu-item-image-area"]');
        const image = imageArea?.querySelector(`img[alt="${menuItem.title}"]`);

        expect(imageArea).toBeTruthy();
        expect(imageArea?.className).toContain('h-[11rem]');
        expect(imageArea?.className).toContain('bg-white');
        expect(imageArea?.className).not.toContain('bg-slate-50');
        expect(image?.className).toContain('object-contain');
        expect(image?.className).toContain('scale-[1.12]');
    });

    it('prepares a compact mobile card variant for dense catalog rows', async () => {
        await mountApp({
            menuItems: [{
                ...menuItem,
                image_display_url: '/storage/menu-items/manual/11/soup.png',
            }],
        });

        const card = document.querySelector('[data-testid="menu-item-card"]');
        const imageArea = document.querySelector('[data-testid="menu-item-image-area"]');
        const meta = document.querySelector('[data-testid="menu-item-meta"]');
        const title = card?.querySelector('h3');
        const priceStepper = document.querySelector('[data-testid="menu-item-price-stepper"]');
        const plusButton = buttonByAriaLabel(`Добавить в заказ: ${menuItem.title}`);
        const addIcon = plusButton?.querySelector('svg');
        const favoriteButton = card?.querySelector('button[aria-pressed]');

        expect(card).toBeTruthy();
        expect(imageArea?.className).toContain('max-[430px]:h-[7.35rem]');
        expect(meta?.className).toContain('max-[430px]:hidden');
        expect(title?.className).toContain('max-[430px]:line-clamp-2');
        expect(title?.className).toContain('max-[430px]:min-h-[2.2rem]');
        expect(title?.getAttribute('title')).toBe(menuItem.title);
        expect(title?.getAttribute('aria-label')).toBe(`Название блюда: ${menuItem.title}`);
        expect(priceStepper?.className).toContain('max-[430px]:h-9');
        expect(priceStepper?.className).toContain('max-[430px]:w-full');
        expect(addIcon?.className).not.toContain('translate-x-px');
        expect(favoriteButton?.className).toContain('max-[430px]:size-8');
    });

    it('keeps a compact inline stepper for mobile tiles without separate quantity badge', async () => {
        await mountApp({
            authenticated: true,
            menuItems: [menuItem],
            order: orderWithItem,
        });

        const compactQuantityButton = document.querySelector('[data-testid="menu-item-compact-quantity-button"]');
        const desktopStepper = document.querySelector('[data-testid="menu-item-price-stepper"]');
        const stepperPrice = desktopStepper?.querySelector('[data-testid="menu-item-stepper-price"]');
        const overlay = document.querySelector('[data-testid="menu-item-quantity-overlay"]');

        expect(compactQuantityButton).toBeNull();
        expect(desktopStepper).toBeTruthy();
        expect(desktopStepper?.className).toContain('max-[430px]:h-9');
        expect(desktopStepper?.className).toContain('max-[430px]:w-full');
        expect(desktopStepper?.className).toContain('bg-blue-700');
        expect(stepperPrice?.className).toContain('max-[430px]:min-w-0');
        expect(overlay?.textContent).toContain('1');
    });

    it('uses image_display_url for order panel thumbnails', async () => {
        await mountApp({
            authenticated: true,
            menuItems: [{
                ...menuItem,
                image_display_url: '/storage/menu-items/manual/11/soup.png',
                image_url: 'https://example.com/supplier-soup.png',
            }],
            order: orderWithItem,
        });

        const orderPanel = document.querySelector('.catalog-order-panel');
        const image = orderPanel?.querySelector(`img[alt="${menuItem.title}"]`);

        expect(image?.getAttribute('src')).toBe('/storage/menu-items/manual/11/soup.png');
    });

    it('exposes the desktop order area as a cart panel', async () => {
        await mountApp({
            authenticated: true,
            order: orderWithItem,
        });

        const panel = document.querySelector('[data-testid="desktop-order-panel"]');

        expect(panel).toBeTruthy();
        expect(panel?.getAttribute('aria-label')).toBe('Панель корзины');
        expect(panel?.className).toContain('xl:sticky');
        expect(panel?.className).toContain('xl:top-[5.75rem]');
        expect(panel?.className).toContain('xl:h-full');
        expect(panel?.querySelector('.cart-panel-compact')).toBeTruthy();
    });

    it('keeps the order total and submit action in a sticky footer', async () => {
        await mountApp({
            authenticated: true,
            order: orderWithItem,
        });

        const itemsScroll = document.querySelector('[data-testid="order-panel-items-scroll"]');
        const footer = document.querySelector('[data-testid="order-panel-footer"]');

        expect(itemsScroll).toBeTruthy();
        expect(itemsScroll?.className).toContain('overflow-y-auto');
        expect(itemsScroll?.className).toContain('scrollbar-none');
        expect(footer).toBeTruthy();
        expect(footer?.className).toContain('sticky');
        expect(footer?.textContent).toContain('Итого');
        expect(footer?.textContent).toContain('Оформить заказ');
    });

    it('sends fridge PATCH actions and reloads fridge data', async () => {
        const { fetchMock } = await mountApp({
            authenticated: true,
            fridgeItems: [fridgeItem],
        });

        await click(document.querySelector('[aria-label="Открыть раздел: Холодильник"]'));
        expect(document.body.textContent).toContain('Мой холодильник');
        expect(document.body.textContent).toContain('Блюда, которые сейчас ждут вас.');
        expect(document.body.textContent).toContain('В холодильнике');
        expect(document.body.textContent).toContain('Порций');
        expect(document.body.textContent).toContain('Скоро истекает');
        expect(document.body.textContent).toContain('До');
        expect(document.querySelector('[data-testid="fridge-panel-scroll"]')?.className).toContain('overflow-y-auto');
        await click(buttonByText('Съел'));

        expect(patchedTo(fetchMock, `/my-fridge/items/${fridgeItem.id}/eat-one`)).toBe(true);
        expect(fetchMock.mock.calls.filter(([url]) => String(url).includes('/my-fridge')).length).toBeGreaterThan(2);
        expect(document.body.textContent).toContain('1 шт.');

        await click(buttonByText('Съел всё'));
        expect(patchedTo(fetchMock, `/my-fridge/items/${fridgeItem.id}/eat-all`)).toBe(true);
    });

    it('sends discard PATCH actions and reloads fridge data', async () => {
        const { fetchMock } = await mountApp({
            authenticated: true,
            fridgeItems: [fridgeItem],
        });

        await click(document.querySelector('[aria-label="Открыть раздел: Холодильник"]'));
        await click(buttonByText('Списать'));

        expect(patchedTo(fetchMock, `/my-fridge/items/${fridgeItem.id}/discard`)).toBe(false);
        expect(document.body.textContent).toContain('Списать блюдо из холодильника?');
        expect(document.body.textContent).not.toContain('Выбросить');

        const confirmWriteOffButton = Array.from(document.querySelectorAll('button')).find(
            (button) => button.textContent?.trim() === 'Списать' && button.className.includes('bg-slate-900'),
        );
        await click(confirmWriteOffButton);
        expect(patchedTo(fetchMock, `/my-fridge/items/${fridgeItem.id}/discard`)).toBe(true);
        expect(fetchMock.mock.calls.filter(([url]) => String(url).includes('/my-fridge')).length).toBeGreaterThan(2);
    });

    it('shows a clear error when a fridge PATCH action fails', async () => {
        await mountApp({
            authenticated: true,
            fridgeItems: [fridgeItem],
            fridgePatchStatus: 403,
        });

        await click(document.querySelector('[aria-label="Открыть раздел: Холодильник"]'));
        await click(buttonByText('Съел'));

        expect(document.body.textContent).toContain('Не удалось обновить холодильник.');
    });

    it('keeps fridge and history panels scrollable', async () => {
        await mountApp({
            authenticated: true,
            fridgeItems: [fridgeItem],
            fridgeHistory: [fridgeHistoryItem],
        });

        await click(document.querySelector('[aria-label="Открыть раздел: Холодильник"]'));
        expect(document.querySelector('[data-testid="fridge-panel-scroll"]')?.className).toContain('overflow-y-auto');

        await click(document.querySelector('[aria-label="Закрыть холодильник"]'));
        await click(document.querySelector('[aria-label^="Открыть профиль:"]'));
        await click(document.querySelector('[data-testid="profile-history-action"]'));
        expect(document.querySelector('[data-testid="history-panel-scroll"]')?.className).toContain('overflow-y-auto');
    });

    it('shows calm empty states for fridge and history', async () => {
        await mountApp({
            authenticated: true,
            fridgeItems: [],
            fridgeHistory: [],
        });

        await click(document.querySelector('[aria-label="Открыть раздел: Холодильник"]'));
        expect(document.body.textContent).toContain('В вашем холодильнике пока ничего нет.');
        expect(document.body.textContent).toContain('Когда заказ будет доставлен, блюда появятся здесь.');

        await click(document.querySelector('[aria-label="Закрыть холодильник"]'));
        await click(document.querySelector('[aria-label^="Открыть профиль:"]'));
        await click(document.querySelector('[data-testid="profile-history-action"]'));
        expect(document.body.textContent).toContain('Моя история');
        expect(document.body.textContent).toContain('Истории пока нет.');
        expect(document.body.textContent).toContain('Когда вы отметите блюдо в холодильнике, оно появится здесь.');
    });

    it('keeps compact fridge cards with safe wrapping and secondary write-off action', async () => {
        const longTitleItem = {
            ...fridgeItem,
            title_snapshot: 'Очень длинное название блюда с множеством слов чтобы не ломать сетку карточки и не вызывать горизонтальный скролл',
        };

        await mountApp({
            authenticated: true,
            fridgeItems: [longTitleItem],
        });

        await click(document.querySelector('[aria-label="Открыть раздел: Холодильник"]'));

        const titleNode = Array.from(document.querySelectorAll('p')).find((node) => node.textContent?.includes('Очень длинное название блюда'));
        expect(titleNode?.className).toContain('line-clamp-2');
        expect(titleNode?.className).toContain('break-words');
        expect(document.body.textContent).toContain('Съел');
        expect(document.body.textContent).toContain('Съел всё');
        expect(document.body.textContent).toContain('Списать');
        expect(document.body.textContent).not.toContain('Выбросить');
        expect(document.querySelector('.overflow-x-hidden')).toBeTruthy();
    });
});
