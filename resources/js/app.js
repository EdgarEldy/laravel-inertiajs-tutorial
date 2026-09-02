import './bootstrap';
import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { i18nVue } from 'laravel-vue-i18n';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(i18nVue, {
                // No `lang` option here on purpose: the plugin reads the
                // current locale straight off the `<html lang="...">`
                // attribute rendered by resources/views/app.blade.php
                // (which itself reflects app()->getLocale(), set by the
                // SetLocale middleware from session). That means the right
                // language is already active on first paint, not just
                // after a language-switcher click.
                resolve: async (lang) => {
                    // The Vite plugin detects lang/en/*.php, lang/fr/*.php
                    // (Laravel's own validation/auth message files) sitting
                    // alongside our lang/en.json, lang/fr.json and, because
                    // of that, also calls this resolver with a "php_"-
                    // prefixed lang (e.g. "php_fr") expecting a second,
                    // PHP-sourced translation set to merge in. The plugin
                    // does generate a real lang/php_en.json / lang/php_fr.json
                    // on disk for this (its buildStart hook), but only for the
                    // duration of the Vite build itself - it deletes them
                    // again in buildEnd, so `import()`-ing one here would
                    // work at build time yet leave nothing to fetch at
                    // runtime. This project also keeps those PHP files
                    // server-only on purpose (Laravel's own Validator reads
                    // them directly, never through Vue), so there is nothing
                    // worth merging in anyway - returning { default: {} }
                    // short-circuits the whole case instead of relying on
                    // that transient file surviving into the request.
                    if (lang.startsWith('php_')) {
                        return { default: {} };
                    }

                    // Returning the raw import() result (not its .default)
                    // is deliberate, not an oversight: the library's own
                    // internal avoidExceptionOnPromise() unwraps `.default`
                    // itself from whatever this resolver's returned promise
                    // settles to - unwrapping it here too meant the library
                    // was reading `.default` off an object that no longer
                    // had one, always getting undefined back and silently
                    // falling back to the fallback language on every call,
                    // load succeeding or not. Confirmed by tracing an actual
                    // language switch end to end (resolve() returning the
                    // correct translations, but the active language and
                    // <html lang> never actually changing) rather than
                    // assumed from reading the library's source alone.
                    return import(`../../lang/${lang}.json`);
                },
            })
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
