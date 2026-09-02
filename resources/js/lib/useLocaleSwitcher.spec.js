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

import { switchLocale, locales, isSwitchingLocale } from './useLocaleSwitcher';

describe('useLocaleSwitcher', () => {
    beforeEach(() => {
        loadLanguageAsyncMock.mockReset();
        routerPostMock.mockReset();
        // isSwitchingLocale is module-level singleton state (the whole point
        // of the guard is that it persists across calls within a real
        // session) - reset it between tests so one test's in-flight switch
        // doesn't leak into the next and make switchLocale() a silent no-op.
        isSwitchingLocale.value = false;
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

    it('resets isSwitchingLocale even when loadLanguageAsync rejects, so a failed switch does not permanently lock the buttons', async () => {
        loadLanguageAsyncMock.mockRejectedValueOnce(new Error('network error'));

        await expect(switchLocale('fr')).rejects.toThrow('network error');

        expect(isSwitchingLocale.value).toBe(false);
    });

    it('ignores a second switchLocale call while one is already in flight', async () => {
        let resolveFirstLoad;
        loadLanguageAsyncMock.mockImplementationOnce(() => new Promise((resolve) => {
            resolveFirstLoad = resolve;
        }));

        const firstCall = switchLocale('fr');
        // isSwitchingLocale is set synchronously before the first await, so
        // by the time this second call starts, the guard is already active.
        const secondCall = switchLocale('en');

        resolveFirstLoad();
        await Promise.all([firstCall, secondCall]);

        // Only the first click's locale should ever have reached
        // loadLanguageAsync - the second click, arriving while the first is
        // still in flight, is exactly the double-click race this guard
        // exists to prevent (see this composable's own doc comment).
        expect(loadLanguageAsyncMock).toHaveBeenCalledTimes(1);
        expect(loadLanguageAsyncMock).toHaveBeenCalledWith('fr');
    });

    it('logs (rather than silently swallowing) a failed locale-persist request', async () => {
        const consoleError = vi.spyOn(console, 'error').mockImplementation(() => {});
        routerPostMock.mockImplementation((url, data, options) => {
            options.onError({ locale: 'The locale could not be saved.' });
            options.onFinish();
        });

        await switchLocale('fr');

        expect(consoleError).toHaveBeenCalledWith(
            'Failed to persist the selected locale in session:',
            { locale: 'The locale could not be saved.' },
        );

        consoleError.mockRestore();
    });
});
