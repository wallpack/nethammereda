import path from 'node:path';
import { defineConfig } from 'vitest/config';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [vue()],
    resolve: {
        alias: {
            '@': path.resolve(__dirname, 'resources/js'),
        },
    },
    test: {
        environment: 'jsdom',
        globals: true,
        include: ['resources/js/**/*.spec.js'],
        exclude: ['node_modules/**', '.tmp-superpowers/**', '.tmp-localhost/**'],
        setupFiles: ['resources/js/test/setup.js'],
    },
});
