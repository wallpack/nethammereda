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
