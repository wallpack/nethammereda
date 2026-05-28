import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import AuthModal from '@/components/AuthModal.vue';

describe('AuthModal telegram login', () => {
    it('renders email/password form and one telegram login link', async () => {
        const wrapper = mount(AuthModal, {
            attachTo: document.body,
            props: {
                open: true,
                email: '',
                password: '',
                rememberMe: false,
                showPassword: false,
                loading: false,
                error: '',
                message: '',
            },
            global: {
                stubs: {
                    DialogRoot: { template: '<div><slot /></div>' },
                    DialogPortal: { template: '<div><slot /></div>' },
                    DialogOverlay: { template: '<div><slot /></div>' },
                    DialogContent: { template: '<div><slot /></div>' },
                    DialogTitle: { template: '<div><slot /></div>' },
                    DialogDescription: { template: '<div><slot /></div>' },
                    DialogClose: { template: '<button type="button"><slot /></button>' },
                },
            },
        });

        const emailInput = document.querySelector('#auth-modal-email');
        const passwordInput = document.querySelector('#auth-modal-password');
        const telegramLinks = document.querySelectorAll('[data-testid="telegram-site-login-link"]');

        expect(emailInput).toBeTruthy();
        expect(passwordInput).toBeTruthy();
        expect(telegramLinks).toHaveLength(1);
        expect(telegramLinks[0]?.getAttribute('href')).toBe('/auth/telegram');
        expect(wrapper.text()).toContain('Войти через Telegram');
        expect(document.querySelector('script[src*="telegram-widget.js"], iframe[src*="telegram.org"]')).toBeNull();

        wrapper.unmount();
    });
});
