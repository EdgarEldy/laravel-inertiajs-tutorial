<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { can } from '@/lib/can';
import type { Paginated } from '@/types/rbac';
import type { Order } from '@/types/catalog';

defineProps<{
    orders: Paginated<Order>;
}>();

const emit = defineEmits<{
    create: [];
}>();
</script>

<template>
    <div class="bg-white shadow-sm sm:rounded-lg p-6">
        <div class="flex items-center justify-between gap-4 mb-4 flex-wrap">
            <SecondaryButton v-if="can('ORDER:WRITE')" @click="emit('create')">
                {{ $t('Create Order') }}
            </SecondaryButton>
        </div>

        <table class="min-w-full divide-y divide-gray-200">
            <thead>
                <tr>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ $t('Customer') }}</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ $t('Product') }}</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ $t('Quantity') }}</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ $t('Total') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <!--
                    No Edit/Delete columns at all, unlike every other
                    resource's Data.vue - orders have no update/destroy
                    route.
                -->
                <tr v-for="order in orders.data" :key="order.id">
                    <td class="px-3 py-2 text-sm text-gray-900">{{ order.customer?.first_name }} {{ order.customer?.last_name }}</td>
                    <td class="px-3 py-2 text-sm text-gray-500">{{ order.product?.product_name }}</td>
                    <td class="px-3 py-2 text-sm text-gray-500">{{ order.quantity }}</td>
                    <td class="px-3 py-2 text-sm text-gray-500">{{ order.total }}</td>
                </tr>
                <tr v-if="orders.data.length === 0">
                    <td colspan="4" class="px-3 py-6 text-center text-sm text-gray-500">{{ $t('No orders found.') }}</td>
                </tr>
            </tbody>
        </table>

        <div v-if="orders.links.length > 3" class="flex flex-wrap gap-1 mt-4">
            <template v-for="link in orders.links" :key="link.label">
                <span
                    v-if="!link.url"
                    class="px-3 py-1 text-sm text-gray-400"
                    v-html="link.label"
                />
                <Link
                    v-else
                    :href="link.url"
                    preserve-scroll
                    class="px-3 py-1 text-sm rounded-md"
                    :class="link.active ? 'bg-indigo-500 text-white' : 'text-gray-700 hover:bg-gray-100'"
                    v-html="link.label"
                />
            </template>
        </div>
    </div>
</template>
