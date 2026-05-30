import { describe, expect, it } from 'vitest';
import { menuItemDisplayMeta, menuItemDisplayTitle } from './menuDisplay';

describe('menu display helpers', () => {
    it('cleans known malformed dish title spacing without changing the stored title', () => {
        const item = { title: 'Запеканка картофельнаяс куриным жульеном' };

        expect(menuItemDisplayTitle(item)).toBe('Запеканка картофельная с куриным жульеном');
        expect(item.title).toBe('Запеканка картофельнаяс куриным жульеном');
    });

    it('prefers API display weight and normalizes compact units for meta display', () => {
        expect(menuItemDisplayMeta({ title: 'Капуста тушеная с сосиской', display_weight: '240гр' })).toBe('240 г');
        expect(menuItemDisplayMeta({ title: 'Напиток', weight: '1л' })).toBe('1 л');
        expect(menuItemDisplayMeta({ title: 'Пирожное', display_weight: '2шт' })).toBe('2 шт');
    });
});
