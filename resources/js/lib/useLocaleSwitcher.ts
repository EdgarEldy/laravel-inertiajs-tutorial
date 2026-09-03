import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { loadLanguageAsync } from 'laravel-vue-i18n';
import { update as updateLocale } from '@/actions/App/Http/Controllers/LocaleController';

export const locales = [
    { code: 'en', label: 'EN' },
    { code: 'fr', label: 'FR' },
];

/**
 * True while a switch is in flight - exposed so the buttons that call
 * switchLocale() can disable themselves for that window. Without this, two
 * rapid clicks on different locales race: both loadLanguageAsync() calls
 * run concurrently, and whichever router.post() lands last on the server
 * wins regardless of which button was actually clicked last, leaving the
 * client-rendered language and the session-persisted one silently
 * disagreeing until the next reload.
 */
export const isSwitchingLocale = ref(false);

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
 *
 * onError logs rather than silently swallowing a failed persist (an
 * expired session, a dropped connection): without it, loadLanguageAsync
 * had already flipped the visible UI to the new language, so the switch
 * *looks* successful right up until the next full page load quietly
 * reverts it with no indication anything went wrong.
 */
export async function switchLocale(locale: string): Promise<void> {
    if (isSwitchingLocale.value) {
        return;
    }

    isSwitchingLocale.value = true;

    try {
        await loadLanguageAsync(locale);

        router.post(updateLocale().url, { locale }, {
            preserveScroll: true,
            onError: (errors) => {
                console.error('Failed to persist the selected locale in session:', errors);
            },
            onFinish: () => {
                isSwitchingLocale.value = false;
            },
        });
    } catch (error) {
        isSwitchingLocale.value = false;
        throw error;
    }
}
