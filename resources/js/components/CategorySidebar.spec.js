import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import CategorySidebar from './CategorySidebar.vue';

const expectClasses = (element, classes) => {
    expect(element.classes()).toEqual(expect.arrayContaining(classes));
};

describe('CategorySidebar', () => {
    it('uses dense plain rows with visible selected states and muted idle states', async () => {
        const wrapper = mount(CategorySidebar, {
            props: {
                categories: [{ id: 1, name: 'Soups' }],
                items: [{ id: 11, category_id: 1 }],
                selectedCategory: 1,
            },
        });

        let [allButton, categoryButton] = wrapper.findAll('button');

        expectClasses(categoryButton, ['h-10', 'rounded-xl', 'bg-blue-50', 'text-blue-800']);
        expectClasses(categoryButton.find('[data-slot="badge"]'), ['bg-blue-100', 'text-blue-800']);
        expectClasses(allButton, ['h-10', 'rounded-xl', 'text-[#595959]', 'hover:bg-slate-50']);
        expect(allButton.classes()).not.toContain('rounded-full');

        await wrapper.setProps({ selectedCategory: null });
        [allButton, categoryButton] = wrapper.findAll('button');

        expectClasses(allButton, ['h-10', 'rounded-xl', 'bg-blue-50', 'text-blue-800']);
        expectClasses(allButton.find('[data-slot="badge"]'), ['bg-blue-100', 'text-blue-800']);
        expectClasses(categoryButton, ['h-10', 'rounded-xl', 'text-[#595959]', 'hover:bg-slate-50']);
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
        expectClasses(row, ['scrollbar-none', 'flex-wrap', 'max-w-full', 'min-w-0', 'xl:flex-col', 'xl:rounded-[1.35rem]']);
        expect(row.classes()).not.toEqual(expect.arrayContaining(['w-max', 'min-w-full', 'flex-nowrap']));
    });

    it('keeps favorites out of the category rail even if append content is provided', () => {
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

        expect(wrapper.find('[data-testid="menu-favorites-chip"]').exists()).toBe(false);
        expect(wrapper.text()).not.toContain('Избранное');
    });
});
