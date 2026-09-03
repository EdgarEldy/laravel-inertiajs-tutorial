import { configDefaults, defineConfig } from 'vitest/config';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [vue()],
    test: {
        environment: 'jsdom',
        globals: true,
        setupFiles: ['./resources/js/test-setup.js'],
        // Vitest's default include glob (**/*.spec.js) also matches
        // Playwright's own spec files under tests/Browser/ - without this
        // exclude, Vitest tries to collect them as its own tests and fails
        // on Playwright's test.describe(), even though they contain zero
        // real Vitest tests.
        exclude: [...configDefaults.exclude, 'tests/Browser/**'],
    },
    resolve: {
        alias: {
            '@': '/resources/js',
        },
    },
});
