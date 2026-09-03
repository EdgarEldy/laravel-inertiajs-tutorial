<script setup>
import { currentLocale } from 'laravel-vue-i18n';
import { locales, switchLocale, isSwitchingLocale } from '@/lib/useLocaleSwitcher';

// A single root element, no explicit inheritAttrs: false - Vue's default
// attribute fallthrough lets every call site (AuthenticationCard.vue,
// AppLayout.vue's desktop and mobile nav) pass its own positioning/spacing
// via a plain `class` prop rather than needing a variant prop for each of
// the three slightly different wrapper contexts. The button styling itself
// stays identical across all three usages, on purpose - the desktop/mobile
// copies this replaces had already drifted from each other (a different
// inactive-state class on the mobile nav), which is exactly the kind of
// divergence a single shared component prevents by construction.
</script>

<template>
    <div class="flex items-center rounded-md border border-gray-200">
        <button
            v-for="locale in locales"
            :key="locale.code"
            type="button"
            :disabled="isSwitchingLocale"
            class="px-2 py-1 text-xs first:rounded-s-md last:rounded-e-md focus:outline-none disabled:opacity-50 disabled:cursor-not-allowed"
            :class="currentLocale === locale.code
                ? 'bg-gray-800 text-white font-semibold'
                : 'text-gray-500 hover:text-gray-700'"
            @click="switchLocale(locale.code)"
        >
            {{ locale.label }}
        </button>
    </div>
</template>
