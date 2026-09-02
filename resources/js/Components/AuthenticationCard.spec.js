import { describe, expect, it, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';

const { loadLanguageAsyncMock, routerPostMock } = vi.hoisted(() => ({
    loadLanguageAsyncMock: vi.fn().mockResolvedValue(),
    routerPostMock: vi.fn(),
}));

vi.mock('laravel-vue-i18n', () => ({
    loadLanguageAsync: loadLanguageAsyncMock,
    currentLocale: { value: 'en' },
}));

vi.mock('@inertiajs/vue3', () => ({
    router: { post: routerPostMock },
}));

vi.mock('@/actions/App/Http/Controllers/LocaleController', () => ({
    update: () => ({ url: '/locale', method: 'post' }),
}));

import AuthenticationCard from './AuthenticationCard.vue';
import { isSwitchingLocale } from '@/lib/useLocaleSwitcher';

describe('AuthenticationCard language switcher', () => {
    beforeEach(() => {
        loadLanguageAsyncMock.mockClear();
        routerPostMock.mockClear();
        // isSwitchingLocale is real (unmocked) module state from
        // useLocaleSwitcher - reset it between tests so one test's
        // in-flight switch doesn't leave the next test's buttons disabled.
        isSwitchingLocale.value = false;
    });

    it('renders one button per supported locale', () => {
        const wrapper = mount(AuthenticationCard);

        const buttons = wrapper.findAll('button');

        expect(buttons).toHaveLength(2);
        expect(buttons.map((button) => button.text())).toEqual(['EN', 'FR']);
    });

    it('calls loadLanguageAsync with "fr" when the FR button is clicked', async () => {
        const wrapper = mount(AuthenticationCard);

        const frButton = wrapper.findAll('button').find((button) => button.text() === 'FR');
        await frButton.trigger('click');

        // Flush the microtask queue so the async switchLocale handler,
        // triggered by the click but not awaited by it, has a chance to run.
        await vi.waitFor(() => {
            expect(loadLanguageAsyncMock).toHaveBeenCalled();
        });

        expect(loadLanguageAsyncMock).toHaveBeenCalledWith('fr');
        // This is precisely the assertion the real bug would have failed:
        // the resolver/loader ran and "succeeded" (no thrown error, no
        // failed network request) but never actually drove the locale the
        // user clicked. Asserting the exact argument passed to the
        // library's own public API, rather than only that it was called,
        // is what would have caught a wiring or double-unwrap regression.
    });

    it('persists the chosen locale to the server after the client-side swap resolves', async () => {
        const wrapper = mount(AuthenticationCard);

        const frButton = wrapper.findAll('button').find((button) => button.text() === 'FR');
        await frButton.trigger('click');

        await vi.waitFor(() => {
            expect(routerPostMock).toHaveBeenCalled();
        });

        expect(routerPostMock).toHaveBeenCalledWith(
            '/locale',
            { locale: 'fr' },
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('renders the default and named slots', () => {
        const wrapper = mount(AuthenticationCard, {
            slots: {
                logo: '<div class="logo-slot">Logo</div>',
                default: '<div class="card-body">Card content</div>',
            },
        });

        expect(wrapper.find('.logo-slot').exists()).toBe(true);
        expect(wrapper.find('.card-body').exists()).toBe(true);
    });
});
