<script setup lang="ts">
import { watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import DialogModal from '@/Components/DialogModal.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import OrderController from '@/actions/App/Http/Controllers/OrderController';
import type { Customer, Product } from '@/types/catalog';

const props = defineProps<{
    show: boolean;
    // Only id + display name - see OrderController::index()'s own comment
    // for why the full Customer/Product shape is never sent here.
    customers: Pick<Customer, 'id' | 'first_name' | 'last_name'>[];
    products: Pick<Product, 'id' | 'product_name'>[];
}>();

const emit = defineEmits<{
    close: [];
}>();

// Create-only: a single `useForm()`, no edit branch at all - unlike every
// other resource's Form.vue, there is no `product`/`editing*` prop to
// pre-fill from and no `update` route to post to.
const form = useForm({
    customer_id: '' as number | string,
    product_id: '' as number | string,
    quantity: '1' as number | string,
});

watch(() => props.show, (show) => {
    if (show) {
        form.customer_id = props.customers[0]?.id ?? '';
        form.product_id = props.products[0]?.id ?? '';
        form.quantity = '1';
        form.clearErrors();
    }
});

const submit = () => {
    form.post(OrderController.store.url(), {
        preserveScroll: true,
        onSuccess: () => close(),
    });
};

const close = () => {
    form.reset();
    form.clearErrors();
    emit('close');
};
</script>

<template>
    <DialogModal :show="show" @close="close">
        <template #title>
            {{ $t('Create Order') }}
        </template>

        <template #content>
            <div>
                <InputLabel for="customer_id" :value="$t('Customer')" />
                <select
                    id="customer_id"
                    v-model="form.customer_id"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
                    <option v-for="customer in customers" :key="customer.id" :value="customer.id">
                        {{ customer.first_name }} {{ customer.last_name }}
                    </option>
                </select>
                <InputError :message="form.errors.customer_id" class="mt-2" />
            </div>

            <div class="mt-4">
                <InputLabel for="product_id" :value="$t('Product')" />
                <select
                    id="product_id"
                    v-model="form.product_id"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
                    <option v-for="product in products" :key="product.id" :value="product.id">
                        {{ product.product_name }}
                    </option>
                </select>
                <InputError :message="form.errors.product_id" class="mt-2" />
            </div>

            <div class="mt-4">
                <InputLabel for="quantity" :value="$t('Quantity')" />
                <TextInput
                    id="quantity"
                    v-model="form.quantity"
                    type="number"
                    min="1"
                    step="1"
                    class="mt-1 block w-full"
                    autofocus
                    @keyup.enter="submit"
                />
                <InputError :message="form.errors.quantity" class="mt-2" />
            </div>
        </template>

        <template #footer>
            <SecondaryButton @click="close">
                {{ $t('Cancel') }}
            </SecondaryButton>

            <PrimaryButton
                class="ms-3"
                :class="{ 'opacity-25': form.processing }"
                :disabled="form.processing"
                @click="submit"
            >
                {{ $t('Create') }}
            </PrimaryButton>
        </template>
    </DialogModal>
</template>
