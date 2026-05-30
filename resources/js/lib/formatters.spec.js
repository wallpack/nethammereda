import { describe, expect, it } from 'vitest';
import { formatCartPrice } from './formatters';

describe('formatCartPrice', () => {
    it('formats cart prices as continuous rounded integer rubles', () => {
        expect(formatCartPrice(100)).toBe('100 ₽');
        expect(formatCartPrice(1000)).toBe('1000 ₽');
        expect(formatCartPrice(10000)).toBe('10000 ₽');
        expect(formatCartPrice(100000)).toBe('100000 ₽');
        expect(formatCartPrice('1016.40')).toBe('1016 ₽');
    });
});
