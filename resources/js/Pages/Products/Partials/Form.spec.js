import { describe, expect, it, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import { nextTick, reactive } from 'vue';

const { postMock, putMock, resetMock, clearErrorsMock, useFormMock } = vi.hoisted(() => ({
    postMock: vi.fn(),
    putMock: vi.fn(),
    resetMock: vi.fn(),
    clearErrorsMock: vi.fn(),
    useFormMock: vi.fn(),
}));

vi.mock('@inertiajs/vue3', () => ({
    useForm: useFormMock,
}));

import Form from './Form.vue';

/**
 * A minimal, controllable stand-in for Inertia's real useForm(): reactive
 * enough to drive v-model in the template, but with post/put/reset/clearErrors
 * as plain spies so submission behavior can be asserted directly instead of
 * relying on a real (network-driving) form implementation.
 */
function fakeUseForm(initial) {
    return reactive({
        ...initial,
        errors: {},
        processing: false,
        post: postMock,
        put: putMock,
        reset: resetMock,
        clearErrors: clearErrorsMock,
    });
}

const makeCategories = () => ([
    { id: 1, category_name: 'Electronics', created_at: '2026-01-01T00:00:00.000000Z' },
    { id: 2, category_name: 'Furniture', created_at: '2026-01-02T00:00:00.000000Z' },
]);

// Stubs only the innermost `Modal.vue` (a real <dialog> element driven by
// `dialog.showModal()`/`dialog.close()`), not `DialogModal.vue` - jsdom in
// this project does not implement `HTMLDialogElement.prototype.showModal`,
// so exercising it here would fail on a test-environment gap, not an
// application bug. See Categories/Partials/Form.spec.js for the established
// precedent of this exact stub.
const mountForm = (props = {}) => mount(Form, {
    props: {
        show: true,
        product: null,
        categories: makeCategories(),
        ...props,
    },
    global: {
        mocks: { $t: (key) => key },
        stubs: {
            Modal: { template: '<div><slot /></div>' },
        },
    },
});

describe('Products/Partials/Form.vue', () => {
    beforeEach(() => {
        postMock.mockClear();
        putMock.mockClear();
        resetMock.mockClear();
        clearErrorsMock.mockClear();
        useFormMock.mockReset();
        useFormMock.mockImplementation(fakeUseForm);
    });

    it('always initializes useForm with blank fields, regardless of the product prop (fields are synced later via a watch on "show", not at construction time)', () => {
        mountForm({ show: false, product: { id: 5, category_id: 1, product_name: 'Laptop', unit_price: '999.99' } });

        expect(useFormMock).toHaveBeenCalledWith({ category_id: '', product_name: '', unit_price: '' });
    });

    it('does NOT pre-fill fields just from being mounted with show already true - the sync only happens on a false-to-true transition of "show"', () => {
        const wrapper = mountForm({ show: true, product: { id: 5, category_id: 1, product_name: 'Laptop', unit_price: '999.99' } });

        expect(wrapper.find('input#product_name').element.value).toBe('');
    });

    it('pre-fills fields from the product being edited once "show" transitions from false to true', async () => {
        const wrapper = mountForm({ show: false, product: { id: 5, category_id: 2, product_name: 'Desk', unit_price: '150.00' } });

        await wrapper.setProps({ show: true });
        await nextTick();

        expect(wrapper.find('select#category_id').element.value).toBe('2');
        expect(wrapper.find('input#product_name').element.value).toBe('Desk');
        expect(wrapper.find('input#unit_price').element.value).toBe('150.00');
    });

    it('resyncs to blank product_name/unit_price and the first category when "show" transitions from false to true with product null (create mode)', async () => {
        const wrapper = mountForm({ show: false, product: null });

        await wrapper.setProps({ show: true });
        await nextTick();

        expect(wrapper.find('select#category_id').element.value).toBe('1');
        expect(wrapper.find('input#product_name').element.value).toBe('');
        expect(wrapper.find('input#unit_price').element.value).toBe('');
    });

    it('shows the create title/button when product is null, the edit title/button otherwise', () => {
        const createWrapper = mountForm({ product: null });
        expect(createWrapper.text()).toContain('Create Product');

        const editWrapper = mountForm({ product: { id: 5, category_id: 1, product_name: 'Laptop', unit_price: '999.99' } });
        expect(editWrapper.text()).toContain('Edit Product');
    });

    it('renders one option per category in the category select', () => {
        const wrapper = mountForm();

        const options = wrapper.find('select#category_id').findAll('option');
        expect(options).toHaveLength(2);
        expect(options[0].text()).toBe('Electronics');
        expect(options[1].text()).toBe('Furniture');
    });

    it('posts to ProductController.store on create', async () => {
        const wrapper = mountForm({ show: true, product: null });

        await wrapper.find('select#category_id').setValue('2');
        await wrapper.find('input#product_name').setValue('Desk');
        await wrapper.find('input#unit_price').setValue('150.00');
        const submitButton = wrapper.findAll('button').find((button) => button.text() === 'Create');
        await submitButton.trigger('click');

        expect(postMock).toHaveBeenCalledTimes(1);
        expect(postMock).toHaveBeenCalledWith('/products', expect.objectContaining({ preserveScroll: true }));
        expect(putMock).not.toHaveBeenCalled();
    });

    it('puts to ProductController.update on edit', async () => {
        const wrapper = mountForm({ show: false, product: { id: 9, category_id: 1, product_name: 'Laptop', unit_price: '999.99' } });
        await wrapper.setProps({ show: true });
        await nextTick();

        const submitButton = wrapper.findAll('button').find((button) => button.text() === 'Save');
        await submitButton.trigger('click');

        expect(putMock).toHaveBeenCalledTimes(1);
        expect(putMock).toHaveBeenCalledWith('/products/9', expect.objectContaining({ preserveScroll: true }));
        expect(postMock).not.toHaveBeenCalled();
    });

    it('renders server-side validation errors passed in via form.errors for category_id, product_name, and unit_price', async () => {
        useFormMock.mockImplementation((initial) => {
            const form = fakeUseForm(initial);
            form.errors.category_id = 'The selected category id is invalid.';
            form.errors.product_name = 'The product name has already been taken.';
            form.errors.unit_price = 'The unit price must be at least 0.';
            return form;
        });

        const wrapper = mountForm({ product: null });

        expect(wrapper.text()).toContain('The selected category id is invalid.');
        expect(wrapper.text()).toContain('The product name has already been taken.');
        expect(wrapper.text()).toContain('The unit price must be at least 0.');
    });

    it('resets and clears errors, then emits close, when Cancel is clicked', async () => {
        const wrapper = mountForm({ product: null });

        const cancelButton = wrapper.findAll('button').find((button) => button.text() === 'Cancel');
        await cancelButton.trigger('click');

        expect(resetMock).toHaveBeenCalledTimes(1);
        expect(clearErrorsMock).toHaveBeenCalledTimes(1);
        expect(wrapper.emitted('close')).toHaveLength(1);
    });
});
