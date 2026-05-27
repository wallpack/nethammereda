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

const jsonResponse = (payload, status = 200) => Promise.resolve({
    ok: status >= 200 && status < 300,
    status,
    json: () => Promise.resolve(payload),
});

const createFetchMock = ({
    authenticated = false,
    authenticatedUser = user,
    order = emptyOrder,
    currentCycle = cycle,
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
} = {}) => {
    let currentOrder = order;
    let currentFridgeItems = [...fridgeItems];
    let currentUser = { ...authenticatedUser };

    return vi.fn((input, options = {}) => {
        const path = String(input).replace('/api', '');
        const method = options.method ?? 'GET';

        if (path === '/me') {
            return authenticated
                ? jsonResponse({ data: currentUser })
                : jsonResponse({ message: 'Unauthenticated.' }, 401);
        }

        if (path === '/me/profile' && method === 'PATCH') {
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
            return jsonResponse({ data: { cycle: currentCycle, order: currentOrder } });
        }

        if (path === '/my-order/items' && method === 'POST') {
            currentOrder = orderWithItem;
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
    const { authenticated = false } = options;

    if (authenticated) {
        localStorage.setItem('lunch_mvp_token', 'test-token');
    }

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

        expect(document.querySelector('[data-testid="week-status-loading"]')).toBeTruthy();
        const menuLoadingGrid = document.querySelector('.dishes-grid[aria-busy="true"]');
        const menuCardSkeleton = menuLoadingGrid?.querySelector('.menu-card [data-slot="skeleton"]');
        expect(menuCardSkeleton?.className).toContain('max-[430px]:h-[8.25rem]');
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

        const weekStatusText = document.querySelector('.week-status')?.textContent ?? '';
        expect(weekStatusText).toContain('Приём заказов закрыт');
        expect(weekStatusText).not.toContain('Недельный цикл не создан');
        expect(weekStatusText).not.toContain('Меню появится после создания цикла администратором.');
    });

    it('renders guest header with a single login action and no old guest copy', async () => {
        await mountApp();

        expect(buttonByText('Войти')).toBeTruthy();
        expect(document.body.textContent).not.toContain('Гость');
        expect(document.body.textContent).not.toContain('Вход в панели заказа');
    });

    it('renders catalog heading without legacy promo copy', async () => {
        await mountApp({
            menuItems: [menuItem, secondMenuItem],
            menuCategories: [category, secondCategory],
        });

        expect(document.querySelector('#menu-heading')?.textContent).toContain('Каталог');
        expect(document.body.textContent).not.toContain('Меню недели');
        expect(document.body.textContent).not.toContain('Что нового');
        expect(document.body.textContent).not.toContain('доступно для заказа');
    });

    it('renders brand with uppercase N', async () => {
        await mountApp();

        const brand = document.querySelector('[aria-label="NethammerEda"]');

        expect(brand).toBeTruthy();
        expect(brand?.textContent?.trim().startsWith('N')).toBe(true);
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

    it('shows guest cart with auth prompt instead of fake order items', async () => {
        await mountApp();

        const panel = document.querySelector('[data-testid="desktop-order-panel"]');
        const panelButtons = Array.from(panel?.querySelectorAll('button') ?? []);
        const footer = panel?.querySelector('[data-testid="order-panel-footer"]');

        expect(panel).toBeTruthy();
        expect(panelButtons).toHaveLength(1);
        expect(footer?.querySelector('button')).toBeTruthy();
        expect(panel?.textContent).toContain('Корзина');
        expect(panel?.textContent).toContain('Войдите, чтобы заказать');
        expect(panel?.textContent).toContain('После входа вы сможете добавить блюда в заказ.');
        expect(panel?.textContent).not.toContain('Вы ещё ничего не добавили');
    });

    it('opens login modal from guest cart CTA', async () => {
        await mountApp();

        const panel = document.querySelector('[data-testid="desktop-order-panel"]');
        const footer = panel?.querySelector('[data-testid="order-panel-footer"]');
        const loginButton = footer?.querySelector('button');

        await click(loginButton);

        expect(document.body.textContent).toContain('Вход в аккаунт');
        expect(document.body.textContent).toContain('Войдите, чтобы оформить заказ.');
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
        expect(document.querySelector('[aria-label="Открыть раздел: Каталог"]')).toBeTruthy();
        expect(document.querySelector('[aria-label="Открыть раздел: Холодильник"]')).toBeTruthy();
        expect(document.querySelector('[aria-label="Открыть раздел: История"]')).toBeTruthy();
        expect(document.querySelector('[aria-label="Открыть раздел: Мой заказ"]')).toBeNull();
        expect(document.querySelector('.catalog-order-panel')).toBeTruthy();
        expect(document.body.textContent).toContain('Корзина');
    });

    it('opens profile modal with account actions', async () => {
        await mountApp({ authenticated: true });

        await click(buttonByText(user.name));

        expect(document.body.textContent).toContain(user.name);
        expect(document.body.textContent).toContain(user.email);
        expect(document.body.textContent).toContain('Избранное');
        expect(document.body.textContent).toContain('Мой заказ');
        expect(document.body.textContent).toContain('Холодильник');
        expect(document.body.textContent).toContain('История питания');
        expect(document.body.textContent).toContain('Укажите ФИО в формате: Фамилия и инициалы. Например: Иванов И.И.');
        expect(document.body.textContent).toContain('Telegram-бот');
        expect(document.body.textContent).toContain('Получайте уведомления о заказах и быстро открывайте меню прямо из Telegram.');
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

        expect(document.querySelector('[data-testid="profile-telegram-linked-text"]')?.textContent).toContain('Telegram подключён');
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

        expect(buttonByText('Избранное')?.getAttribute('aria-pressed')).toBe('true');
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
        expect(document.body.textContent).not.toContain(user.name);
        expect(buttonByText('Войти')).toBeTruthy();
        expect(document.querySelector('.catalog-order-panel')).toBeTruthy();
        expect(document.body.textContent).toContain('Войдите, чтобы заказать');
    });

    it('lets authenticated users add items to the order', async () => {
        const { fetchMock } = await mountApp({ authenticated: true });

        await click(buttonByText('Добавить'));

        expect(postedTo(fetchMock, '/my-order/items')).toBe(true);
        expect(document.body.textContent).toContain('1 позиция');
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
        expect(selectedCategorySummary?.textContent ?? '').toContain('1 блюдо');
    });

    it('filters the catalog to locally selected favorites', async () => {
        await mountApp({
            authenticated: true,
            menuItems: [menuItem, secondMenuItem],
            menuCategories: [category, secondCategory],
        });

        await click(document.querySelector(`[aria-label="Добавить в избранное: ${menuItem.title}"]`));
        await click(buttonByText('Избранное'));

        expect(document.body.textContent).toContain(menuItem.title);
        expect(document.body.textContent).not.toContain(secondMenuItem.title);
    });

    it('shows the catalog as orderable when the cycle can accept orders', async () => {
        await mountApp({ authenticated: true });

        expect(document.body.textContent).toContain('Заказ открыт');
        expect(buttonByText('Добавить')?.disabled).toBe(false);
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

        expect(document.body.textContent).toContain('Приём заказов закрыт');
        expect(document.body.textContent).not.toContain('Заказ закрыт');
        expect(buttonByText('Добавить')?.disabled).toBe(true);
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

        const weekStatusText = document.querySelector('.week-status')?.textContent ?? '';
        expect(weekStatusText).toContain('Приём заказов закрыт');
        expect(weekStatusText).not.toContain('Проверьте холодильник.');
    });

    it('shows a reopen action for a submitted order before the deadline', async () => {
        await mountApp({
            authenticated: true,
            menuItems: [menuItem, secondMenuItem],
            menuCategories: [category, secondCategory],
            order: submittedOrder,
        });

        expect(buttonByText('Редактировать заказ')).toBeTruthy();
        expect(document.body.textContent).toContain('Заказ отправлен · Можно редактировать до');
        expect(document.querySelector(`[aria-label="Увеличить количество: ${menuItem.title}"]`)).toBeNull();
        expect(buttonByText('Добавить')?.disabled).toBe(true);
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
        expect(pageText).toContain('29.05, 12:00');
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

        expect(currentCycleRequestCount).toBeGreaterThan(1);
        expect(document.body.textContent).toContain('Приём заказов закрыт');
        expect(document.body.textContent).not.toContain('Можно редактировать до');
        expect(document.querySelector(`[aria-label="Увеличить количество: ${menuItem.title}"]`)).toBeNull();

        wrapper.unmount();
        vi.useRealTimers();
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
        const addButton = buttonByText('Добавить');

        expect(pageText).toContain('Приём заказов закрыт');
        expect(pageText).not.toContain('Можно редактировать до');
        expect(buttonByText('Редактировать заказ')).toBeFalsy();
        expect(addButton === undefined || addButton.disabled).toBe(true);
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
        expect(document.querySelector(`[aria-label="Увеличить количество: ${menuItem.title}"]`)).toBeTruthy();
        expect(buttonByText('Оформить заказ')?.disabled).toBe(false);
        expect(buttonByText('Добавить')?.disabled).toBe(false);
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
        expect(document.body.textContent).toContain('Приём заказов закрыт');
        expect(document.querySelector(`[aria-label="Увеличить количество: ${menuItem.title}"]`)).toBeNull();
        expect(buttonByText('Добавить')?.disabled).toBe(true);

        await click(document.querySelector('[aria-label="Открыть раздел: Заказ"]'));
        const mobileOrderText = document.querySelector('[data-testid="mobile-order-panel"]')?.textContent ?? '';
        expect(mobileOrderText).toContain('Приём заказов закрыт');
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

        expect(document.body.textContent).toContain('Приём заказов закрыт');
        expect(document.querySelector(`[aria-label="Увеличить количество: ${menuItem.title}"]`)).toBeNull();
        expect(buttonByText('Добавить')?.disabled).toBe(true);

        await click(document.querySelector('[aria-label="Открыть раздел: Заказ"]'));
        const mobileOrderText = document.querySelector('[data-testid="mobile-order-panel"]')?.textContent ?? '';
        expect(mobileOrderText).toContain('Приём заказов закрыт');
        expect(mobileOrderText).not.toContain('отправьте заказ до дедлайна');
    });

    it('opens mobile order, fridge and history sheets from bottom navigation', async () => {
        await mountApp({ authenticated: true });

        await click(document.querySelector('[aria-label="Открыть раздел: Заказ"]'));
        expect(document.querySelector('[data-testid="mobile-order-panel"]')).toBeTruthy();

        await click(document.querySelector('[aria-label="Закрыть мой заказ"]'));
        await click(document.querySelector('[aria-label="Открыть раздел: Холодильник"]'));
        expect(document.querySelector('[data-testid="mobile-fridge-panel"]')).toBeTruthy();

        await click(document.querySelector('[aria-label="Закрыть холодильник"]'));
        await click(document.querySelector('[aria-label="Открыть раздел: История"]'));
        expect(document.querySelector('[data-testid="mobile-history-panel"]')).toBeTruthy();
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
        await click(document.querySelector('[aria-label="Открыть раздел: Заказ"]'));
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

        expect(document.body.textContent).toContain('Фото блюда появится скоро');
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
        expect(imageArea?.className).toContain('h-[16rem]');
        expect(image?.className).toContain('object-contain');
        expect(image?.className).toContain('scale-[1.02]');
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
        const addButton = document.querySelector('[data-testid="menu-item-add-button"]');
        const addIcon = addButton?.querySelector('svg');
        const favoriteButton = card?.querySelector('button[aria-pressed]');

        expect(card).toBeTruthy();
        expect(imageArea?.className).toContain('max-[430px]:h-[8.25rem]');
        expect(meta?.className).toContain('max-[430px]:hidden');
        expect(title?.className).toContain('max-[430px]:line-clamp-3');
        expect(title?.className).toContain('max-[430px]:min-h-[3.15rem]');
        expect(title?.getAttribute('title')).toBe(menuItem.title);
        expect(title?.getAttribute('aria-label')).toBe(`Название блюда: ${menuItem.title}`);
        expect(addButton?.className).toContain('max-[430px]:size-10');
        expect(addButton?.className).toContain('max-[430px]:text-[0px]');
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
        const desktopStepper = document.querySelector('[data-testid="menu-item-stepper"]');
        const stepperQuantity = desktopStepper?.querySelector('span');

        expect(compactQuantityButton).toBeNull();
        expect(desktopStepper).toBeTruthy();
        expect(desktopStepper?.className).toContain('max-[430px]:h-9');
        expect(desktopStepper?.className).toContain('max-[430px]:w-[5.9rem]');
        expect(desktopStepper?.className).toContain('max-[430px]:grid-cols-[2rem_minmax(1.5rem,1fr)_2rem]');
        expect(stepperQuantity?.className).toContain('max-[430px]:min-w-0');
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
        expect(panel?.className).toContain('xl:mt-[7.25rem]');
        expect(panel?.className).toContain('xl:h-[calc(100dvh-18.75rem)]');
    });

    it('keeps the order total and submit action in a sticky footer', async () => {
        await mountApp({
            authenticated: true,
            order: orderWithItem,
        });

        const footer = document.querySelector('[data-testid="order-panel-footer"]');

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
        expect(document.body.textContent).toContain('Годен до');
        await click(buttonByText('Съел 1'));

        expect(patchedTo(fetchMock, `/my-fridge/items/${fridgeItem.id}/eat-one`)).toBe(true);
        expect(fetchMock.mock.calls.filter(([url]) => String(url).includes('/my-fridge')).length).toBeGreaterThan(2);
        expect(document.body.textContent).toContain('остаток 1/2');

        await click(buttonByText('Съел всё'));
        expect(patchedTo(fetchMock, `/my-fridge/items/${fridgeItem.id}/eat-all`)).toBe(true);
    });

    it('sends discard PATCH actions and reloads fridge data', async () => {
        const { fetchMock } = await mountApp({
            authenticated: true,
            fridgeItems: [fridgeItem],
        });

        await click(document.querySelector('[aria-label="Открыть раздел: Холодильник"]'));
        await click(buttonByText('Выбросить'));

        expect(patchedTo(fetchMock, `/my-fridge/items/${fridgeItem.id}/discard`)).toBe(false);
        expect(document.body.textContent).toContain('Выбросить блюдо?');

        await click(buttonByText('Подтвердить выброс'));
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
        await click(buttonByText('Съел 1'));

        expect(document.body.textContent).toContain('Не удалось обновить холодильник.');
    });
});
