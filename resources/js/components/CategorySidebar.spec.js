import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import CategorySidebar from './CategorySidebar.vue';

const expectClasses = (element, classes) => {
    expect(element.classes()).toEqual(expect.arrayContaining(classes));
};

describe('CategorySidebar', () => {
    it('uses visible selected states and muted idle states in category navigation', async () => {
        const wrapper = mount(CategorySidebar, {
            props: {
                categories: [{ id: 1, name: 'Soups' }],
                items: [{ id: 11, category_id: 1 }],
                selectedCategory: 1,
            },
        });

        let [allButton, categoryButton] = wrapper.findAll('button');

        expectClasses(categoryButton, ['bg-amber-50', 'text-amber-900', 'ring-1', 'ring-amber-200/60']);
        expectClasses(categoryButton.find('[data-slot="badge"]'), ['xl:bg-amber-100', 'xl:text-amber-800']);
        expectClasses(allButton, ['text-slate-700', 'hover:bg-slate-50', 'hover:text-slate-900']);

        await wrapper.setProps({ selectedCategory: null });
        [allButton, categoryButton] = wrapper.findAll('button');

        expectClasses(allButton, ['bg-amber-50', 'text-amber-900', 'ring-1', 'ring-amber-200/60']);
        expectClasses(allButton.find('[data-slot="badge"]'), ['xl:bg-amber-100', 'xl:text-amber-800']);
        expectClasses(categoryButton, ['text-slate-700', 'hover:bg-slate-50', 'hover:text-slate-900']);
    });

    it('keeps categories wrapping-safe on mobile and prepared as a desktop rail', () => {
        const wrapper = mount(CategorySidebar, {
            props: {
                categories: [
                    { id: 1, name: 'Soups' },
                    { id: 2, name: 'Mains' },
                    { id: 3, name: 'Bakery' },
                ],
                items: [
                    { id: 11, category_id: 1 },
                    { id: 12, category_id: 2 },
                    { id: 13, category_id: 3 },
                ],
                selectedCategory: null,
            },
        });

        const nav = wrapper.get('nav');
        const row = wrapper.get('[data-testid="category-chip-row"]');

        expectClasses(nav, ['max-w-full', 'min-w-0']);
        expectClasses(row, ['flex-wrap', 'max-w-full', 'min-w-0', 'xl:flex-col', 'xl:rounded-[1.35rem]']);
        expect(row.classes()).not.toEqual(expect.arrayContaining(['w-max', 'min-w-full', 'flex-nowrap']));
    });

    it('renders one appended favorite chip in the shared flow', () => {
        const wrapper = mount(CategorySidebar, {
            props: {
                categories: [{ id: 1, name: 'Soups' }],
                items: [{ id: 11, category_id: 1 }],
                selectedCategory: null,
            },
            slots: {
                append: '<button data-testid="menu-favorites-chip">Избранное</button>',
            },
        });

        const favoriteChips = wrapper.findAll('[data-testid="menu-favorites-chip"]');

        expect(favoriteChips).toHaveLength(1);
    });
});
