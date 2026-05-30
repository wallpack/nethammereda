import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import HistoryPanel from './HistoryPanel.vue';

const historyItem = {
    id: 77,
    title_snapshot: 'Курица терияки с рисом',
    status: 'eaten',
    eaten_at: '2026-05-30T09:20:00.000000Z',
};

const mountPanel = (props = {}) => mount(HistoryPanel, {
    props: {
        fridgeHistory: [historyItem],
        fridgeLoading: false,
        showHeading: true,
        orderSkeletonRows: [1, 2],
        ...props,
    },
});

describe('HistoryPanel UI', () => {
    it('renders readable customer-system rows with status chip and secondary time', () => {
        const wrapper = mountPanel();

        const heading = wrapper.get('h2');
        const subtitle = wrapper.get('[data-testid="history-panel-subtitle"]');
        const group = wrapper.get('[data-testid="history-panel-group-label"]');
        const row = wrapper.get('[data-testid="history-panel-row"]');
        const title = wrapper.get('[data-testid="history-panel-title"]');
        const status = wrapper.get('[data-testid="history-panel-status-chip"]');
        const time = wrapper.get('[data-testid="history-panel-time"]');

        expect(wrapper.find('[data-testid="history-panel-scroll"]').exists()).toBe(true);
        expect(heading.classes()).toContain('customer-heading');
        expect(subtitle.classes()).toContain('customer-muted');
        expect(group.classes()).toContain('customer-meta');
        expect(row.classes()).toContain('customer-row-card');
        expect(title.classes()).toContain('customer-title');
        expect(status.text()).toContain('Съедено');
        expect(status.classes()).toContain('customer-badge');
        expect(time.text()).not.toBe('—');
        expect(time.classes()).toContain('customer-meta');
        expect(row.classes().join(' ')).not.toContain('border-slate-200');
    });

    it('shows empty state when history is empty', () => {
        const wrapper = mountPanel({
            fridgeHistory: [],
        });

        expect(wrapper.text()).toContain('Истории пока нет.');
    });
});
