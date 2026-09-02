import { describe, expect, it, vi, beforeEach } from 'vitest';

const { loadLanguageAsyncMock, routerPostMock } = vi.hoisted(() => ({
    loadLanguageAsyncMock: vi.fn(),
    routerPostMock: vi.fn(),
}));

vi.mock('laravel-vue-i18n', () => ({
    loadLanguageAsync: loadLanguageAsyncMock,
}));

vi.mock('@inertiajs/vue3', () => ({
    router: { post: routerPostMock },
}));

vi.mock('@/actions/App/Http/Controllers/LocaleController', () => ({
    update: () => ({ url: '/locale', method: 'post' }),
}));

import { switchLocale, locales } from './useLocaleSwitcher';

describe('useLocaleSwitcher', () => {
    beforeEach(() => {
        loadLanguageAsyncMock.mockReset();
        routerPostMock.mockReset();
    });

    it('exposes exactly the en/fr locales the switcher renders', () => {
        expect(locales).toEqual([
            { code: 'en', label: 'EN' },
            { code: 'fr', label: 'FR' },
        ]);
    });

    it('calls loadLanguageAsync with the exact locale requested', async () => {
        await switchLocale('fr');

        expect(loadLanguageAsyncMock).toHaveBeenCalledTimes(1);
        expect(loadLanguageAsyncMock).toHaveBeenCalledWith('fr');
    });

    it('awaits loadLanguageAsync before posting to the server', async () => {
        const callOrder = [];

        loadLanguageAsyncMock.mockImplementation(async (locale) => {
            // Simulate real async work (a dynamic import) so that, if the
            // implementation ever stopped awaiting this call, router.post
            // would fire first and this assertion would catch it.
            await new Promise((resolve) => setTimeout(resolve, 0));
            callOrder.push(`loadLanguageAsync:${locale}`);
        });

        routerPostMock.mockImplementation((url, data) => {
            callOrder.push(`router.post:${url}:${JSON.stringify(data)}`);
        });

        await switchLocale('fr');

        expect(callOrder).toEqual([
            'loadLanguageAsync:fr',
            'router.post:/locale:{"locale":"fr"}',
        ]);
    });

    it('posts the chosen locale to the locale update endpoint with preserveScroll', async () => {
        await switchLocale('fr');

        expect(routerPostMock).toHaveBeenCalledTimes(1);
        expect(routerPostMock).toHaveBeenCalledWith(
            '/locale',
            { locale: 'fr' },
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('never calls router.post before loadLanguageAsync has resolved, even if loadLanguageAsync rejects', async () => {
        loadLanguageAsyncMock.mockRejectedValueOnce(new Error('network error'));

        await expect(switchLocale('fr')).rejects.toThrow('network error');

        expect(routerPostMock).not.toHaveBeenCalled();
    });
});
