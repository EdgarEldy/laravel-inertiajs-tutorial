<script setup>
import { currentLocale } from 'laravel-vue-i18n';
import { locales, switchLocale } from '@/lib/useLocaleSwitcher';
</script>

<template>
    <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gray-100">
        <!-- Every unauthenticated page (login, register, password reset, ...) shares
             this single wrapper, so the language switcher lives here rather than
             duplicated across each one - a visitor's very first page still needs a
             way to change language before AppLayout.vue (the authenticated layout,
             which carries its own copy of this same switcher) is ever reached. -->
        <div class="absolute top-4 right-4 flex items-center rounded-md border border-gray-200 bg-white">
            <button
                v-for="locale in locales"
                :key="locale.code"
                type="button"
                class="px-2 py-1 text-xs first:rounded-s-md last:rounded-e-md focus:outline-none"
                :class="currentLocale === locale.code
                    ? 'bg-gray-800 text-white font-semibold'
                    : 'text-gray-500 hover:text-gray-700'"
                @click="switchLocale(locale.code)"
            >
                {{ locale.label }}
            </button>
        </div>

        <div>
            <slot name="logo" />
        </div>

        <div class="w-full sm:max-w-md mt-6 px-6 py-4 bg-white shadow-md overflow-hidden sm:rounded-lg">
            <slot />
        </div>
    </div>
</template>
