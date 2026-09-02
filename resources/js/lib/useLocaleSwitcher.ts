import { router } from '@inertiajs/vue3';
import { loadLanguageAsync } from 'laravel-vue-i18n';
import { update as updateLocale } from '@/actions/App/Http/Controllers/LocaleController';

export const locales = [
    { code: 'en', label: 'EN' },
    { code: 'fr', label: 'FR' },
];

/**
 * Swap the active translations client-side, then persist the choice
 * server-side in session for the next full page load.
 *
 * loadLanguageAsync swaps the active translations for the current page
 * immediately, client-side - the router.post() call only persists the
 * choice in session so the *next* full page load (a fresh visit, or this
 * same page reloaded) renders in that language from the start. Without the
 * loadLanguageAsync call (or without awaiting it before the post fires),
 * clicking the switcher would post successfully but leave every
 * already-rendered $t() call showing the old language until a manual
 * refresh - this is exactly the sequencing this composable's own tests
 * assert on, since the underlying bug it guards against (see app.js) is
 * silent: no error, no failed request, just a language that never changes.
 */
export async function switchLocale(locale: string): Promise<void> {
    await loadLanguageAsync(locale);

    router.post(updateLocale().url, { locale }, {
        preserveScroll: true,
    });
}
