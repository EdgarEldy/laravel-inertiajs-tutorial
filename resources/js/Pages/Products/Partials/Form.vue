<script setup lang="ts">
import { watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import DialogModal from '@/Components/DialogModal.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import ProductController from '@/actions/App/Http/Controllers/ProductController';
import type { Category, Product } from '@/types/catalog';

const props = defineProps<{
    show: boolean;
    product: Product | null;
    categories: Category[];
}>();

const emit = defineEmits<{
    close: [];
}>();

// A single `useForm()` covers both create and edit, following the same
// watch-driven sync pattern as `Categories/Partials/Form.vue` - see the
// comment on `Index.vue` for why this component is never remounted/keyed.
const form = useForm({
    category_id: '' as number | string,
    product_name: '',
    unit_price: '',
});

watch(() => props.show, (show) => {
    if (show) {
        form.category_id = props.product?.category_id ?? (props.categories[0]?.id ?? '');
        form.product_name = props.product?.product_name ?? '';
        form.unit_price = props.product?.unit_price ?? '';
        form.clearErrors();
    }
});

const submit = () => {
    if (props.product) {
        form.put(ProductController.update.url(props.product.id), {
            preserveScroll: true,
            onSuccess: () => close(),
        });
    } else {
        form.post(ProductController.store.url(), {
            preserveScroll: true,
            onSuccess: () => close(),
        });
    }
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
            {{ product ? $t('Edit Product') : $t('Create Product') }}
        </template>

        <template #content>
            <div>
                <InputLabel for="category_id" :value="$t('Category')" />
                <select
                    id="category_id"
                    v-model="form.category_id"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
                    <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                        {{ cat.category_name }}
                    </option>
                </select>
                <InputError :message="form.errors.category_id" class="mt-2" />
            </div>

            <div class="mt-4">
                <InputLabel for="product_name" :value="$t('Product name')" />
                <TextInput
                    id="product_name"
                    v-model="form.product_name"
                    type="text"
                    class="mt-1 block w-full"
                    autofocus
                    @keyup.enter="submit"
                />
                <InputError :message="form.errors.product_name" class="mt-2" />
            </div>

            <div class="mt-4">
                <InputLabel for="unit_price" :value="$t('Unit price')" />
                <TextInput
                    id="unit_price"
                    v-model="form.unit_price"
                    type="number"
                    step="0.01"
                    min="0"
                    class="mt-1 block w-full"
                    @keyup.enter="submit"
                />
                <InputError :message="form.errors.unit_price" class="mt-2" />
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
                {{ product ? $t('Save') : $t('Create') }}
            </PrimaryButton>
        </template>
    </DialogModal>
</template>
