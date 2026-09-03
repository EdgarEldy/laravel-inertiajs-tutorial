<script setup>
import LanguageSwitcher from '@/Components/LanguageSwitcher.vue';
</script>

<template>
    <div class="relative min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gray-100">
        <!-- Every unauthenticated page (login, register, password reset, ...) shares
             this single wrapper, so the language switcher lives here rather than
             duplicated across each one - a visitor's very first page still needs a
             way to change language before AppLayout.vue (the authenticated layout,
             which carries its own copy of this same switcher) is ever reached.
             `relative` on this wrapper is required, not decorative: without it the
             switcher's `absolute` positioning below has no containing block of its
             own to anchor to, and falls back to the initial containing block
             instead - positioned correctly by coincidence on a short page, adrift
             on a longer one. -->
        <LanguageSwitcher class="absolute top-4 right-4 bg-white" />

        <div>
            <slot name="logo" />
        </div>

        <div class="w-full sm:max-w-md mt-6 px-6 py-4 bg-white shadow-md overflow-hidden sm:rounded-lg">
            <slot />
        </div>
    </div>
</template>
