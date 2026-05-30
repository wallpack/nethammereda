import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import UserProfileModal from './UserProfileModal.vue';

const user = {
    id: 7,
    name: 'Test User',
    email: 'user@example.com',
};

const historyOrder = {
    id: 91,
    status: 'submitted',
    submitted_at: '2026-05-29T09:00:00.000000Z',
    total_price: '1565.00',
    items_count: 5,
    can_repeat: true,
    items: [
        { id: 1, title: 'Dish 1', quantity: 1 },
        { id: 2, title: 'Dish 2', quantity: 1 },
        { id: 3, title: 'Dish 3', quantity: 1 },
        { id: 4, title: 'Dish 4', quantity: 1 },
        { id: 5, title: 'Dish 5', quantity: 1 },
    ],
};

const baseProps = {
    open: true,
    user,
    favoritesCount: 0,
    profileSaving: false,
    profileError: '',
    telegramLinked: false,
    telegramLinkAvailable: true,
    telegramLoading: false,
    telegramError: '',
    orderHistory: [historyOrder],
    orderHistoryLoading: false,
    orderHistoryError: '',
    canRepeatHistory: true,
    repeatActionLoading: false,
};

const dialogStubs = {
    DialogRoot: { template: '<div><slot /></div>' },
    DialogPortal: { template: '<div><slot /></div>' },
    DialogOverlay: { template: '<div><slot /></div>' },
    DialogContent: { template: '<div><slot /></div>' },
    DialogTitle: { template: '<div><slot /></div>' },
    DialogDescription: { template: '<div><slot /></div>' },
    DialogClose: { template: '<button type="button"><slot /></button>' },
};

const mountModal = (props = {}) => mount(UserProfileModal, {
    props: {
        ...baseProps,
        ...props,
    },
    global: {
        stubs: dialogStubs,
    },
});

describe('UserProfileModal order history tab', () => {
    it('uses the refreshed customer profile visual system', () => {
        const wrapper = mountModal({
            telegramLinked: true,
            telegramLinkAvailable: true,
            user: { ...user, telegram_id: '9551' },
            favoritesCount: 3,
        });

        const content = wrapper.get('[data-testid="profile-modal-content"]');
        const header = wrapper.get('[data-testid="profile-modal-header"]');
        const avatar = wrapper.get('[data-testid="profile-avatar"]');
        const tabs = wrapper.get('[data-testid="profile-tabs"]');
        const form = wrapper.get('[data-testid="profile-form-card"]');
        const favorites = wrapper.get('[data-testid="profile-favorites-action"]');
        const quickActions = wrapper.get('[data-testid="profile-quick-actions"]');
        const telegram = wrapper.get('[data-testid="profile-telegram-card"]');
        const logout = wrapper.get('[data-testid="profile-logout-button"]');

        expect(content.classes()).toContain('customer-app');
        expect(content.classes()).toContain('rounded-[1.75rem]');
        expect(content.classes()).toContain('shadow-2xl');
        expect(header.classes()).toContain('gap-3.5');
        expect(avatar.classes()).toContain('bg-blue-50');
        expect(wrapper.get('[data-testid="profile-name"]').classes()).toContain('customer-heading');
        expect(tabs.classes()).toContain('bg-[#f2f4f7]');
        expect(tabs.classes()).toContain('rounded-full');
        expect(form.classes()).toContain('customer-soft-card');
        expect(favorites.classes()).toContain('customer-row-card');
        expect(quickActions.text()).toContain('Быстрые действия');
        expect(quickActions.classes()).toContain('customer-soft-card');
        expect(quickActions.text()).not.toContain('БЫСТРЫЕ ДЕЙСТВИЯ');
        expect(telegram.classes()).toContain('customer-soft-card');
        expect(wrapper.get('[data-testid="profile-telegram-linked"]').text()).toContain('Подключён');
        expect(logout.classes()).toContain('text-rose-700');
    });

    it('renders tab "Уже заказывали" in profile modal', () => {
        const wrapper = mountModal();

        expect(wrapper.find('[data-testid="profile-tab-ordered"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="profile-tab-ordered"]').text()).toContain('Уже заказывали');
    });

    it('renders submitted orders in "Уже заказывали" tab with repeat button', async () => {
        const wrapper = mountModal();

        await wrapper.get('[data-testid="profile-tab-ordered"]').trigger('click');

        expect(wrapper.find('[data-testid="profile-orders-tab-panel"]').exists()).toBe(true);
        expect(wrapper.text()).toContain('Dish 1');
        expect(wrapper.findAll('[data-testid="profile-repeat-order-button"]')).toHaveLength(1);
        expect(wrapper.find('[data-testid="profile-history-expand-button"]').exists()).toBe(true);
        expect(wrapper.text()).toContain('Показать всё (1)');
    });

    it('expands and collapses full order list in "Уже заказывали"', async () => {
        const wrapper = mountModal();

        await wrapper.get('[data-testid="profile-tab-ordered"]').trigger('click');

        expect(wrapper.text()).not.toContain('Dish 5');

        await wrapper.get('[data-testid="profile-history-expand-button"]').trigger('click');

        expect(wrapper.text()).toContain('Dish 5');
        expect(wrapper.text()).toContain('Свернуть');

        await wrapper.get('[data-testid="profile-history-expand-button"]').trigger('click');

        expect(wrapper.text()).not.toContain('Dish 5');
    });

    it('shows empty state when history is empty', async () => {
        const wrapper = mountModal({
            orderHistory: [],
        });

        await wrapper.get('[data-testid="profile-tab-ordered"]').trigger('click');

        expect(wrapper.find('[data-testid="profile-order-history-empty"]').exists()).toBe(true);
        expect(wrapper.text()).toContain('Вы ещё не оформляли заказы.');
    });

    it('disables repeat and shows hint when cycle is closed', async () => {
        const wrapper = mountModal({
            canRepeatHistory: false,
        });

        await wrapper.get('[data-testid="profile-tab-ordered"]').trigger('click');

        const repeatButton = wrapper.get('[data-testid="profile-repeat-order-button"]');
        expect(repeatButton.element.disabled).toBe(true);
        expect(wrapper.find('[data-testid="profile-repeat-closed-hint"]').exists()).toBe(true);
    });

    it('emits repeat-order from history tab', async () => {
        const wrapper = mountModal();

        await wrapper.get('[data-testid="profile-tab-ordered"]').trigger('click');
        await wrapper.get('[data-testid="profile-repeat-order-button"]').trigger('click');

        expect(wrapper.emitted('repeat-order')?.[0]).toEqual([historyOrder]);
    });
});
