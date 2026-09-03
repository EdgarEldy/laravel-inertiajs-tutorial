<script setup lang="ts">
import { watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import DialogModal from '@/Components/DialogModal.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import CategoryController from '@/actions/App/Http/Controllers/CategoryController';
import type { Category } from '@/types/catalog';

const props = defineProps<{
    show: boolean;
    category: Category | null;
}>();

const emit = defineEmits<{
    close: [];
}>();

// A single `useForm()` covers both create and edit - the modal posts to
// `store` when `category` is null, `update` when editing an existing one.
// This component instance is never remounted (`Index.vue` deliberately
// passes no `:key`, see the comment there), so the fields are synced from
// `category` here via a `watch` on `show` each time the modal opens,
// instead of relying on a fresh `useForm()` call per editing target.
const form = useForm({
    category_name: '',
});

watch(() => props.show, (show) => {
    if (show) {
        form.category_name = props.category?.category_name ?? '';
        form.clearErrors();
    }
});

const submit = () => {
    if (props.category) {
        form.put(CategoryController.update.url(props.category.id), {
            preserveScroll: true,
            onSuccess: () => close(),
        });
    } else {
        form.post(CategoryController.store.url(), {
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
            {{ category ? $t('Edit Category') : $t('Create Category') }}
        </template>

        <template #content>
            <div>
                <InputLabel for="category_name" :value="$t('Category name')" />
                <TextInput
                    id="category_name"
                    v-model="form.category_name"
                    type="text"
                    class="mt-1 block w-full"
                    autofocus
                    @keyup.enter="submit"
                />
                <InputError :message="form.errors.category_name" class="mt-2" />
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
                {{ category ? $t('Save') : $t('Create') }}
            </PrimaryButton>
        </template>
    </DialogModal>
</template>
