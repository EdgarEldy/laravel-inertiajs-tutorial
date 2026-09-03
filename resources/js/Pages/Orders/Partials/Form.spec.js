import { describe, expect, it, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import { nextTick, reactive } from 'vue';

const { postMock, resetMock, clearErrorsMock, useFormMock } = vi.hoisted(() => ({
    postMock: vi.fn(),
    resetMock: vi.fn(),
    clearErrorsMock: vi.fn(),
    useFormMock: vi.fn(),
}));

vi.mock('@inertiajs/vue3', () => ({
    useForm: useFormMock,
}));

vi.mock('laravel-vue-i18n', () => ({
    trans: (key, replacements = {}) => Object.entries(replacements).reduce(
        (message, [search, value]) => message.replace(`:${search}`, value),
        key,
    ),
}));

import Form from './Form.vue';

/**
 * A minimal, controllable stand-in for Inertia's real useForm() - see
 * Products/Partials/Form.spec.js for the same established pattern.
 */
function fakeUseForm(initial) {
    return reactive({
        ...initial,
        errors: {},
        processing: false,
        post: postMock,
        reset: resetMock,
        clearErrors: clearErrorsMock,
    });
}

const makeCustomers = () => ([
    { id: 1, first_name: 'Jane', last_name: 'Doe', telephone: '555-0100', email: 'jane@example.com', address: '123 Main St' },
    { id: 2, first_name: 'John', last_name: 'Smith', telephone: '555-0199', email: 'john@example.com', address: '456 Oak Ave' },
]);

const makeProducts = () => ([
    { id: 1, category_id: 1, product_name: 'Laptop', unit_price: '999.99' },
    { id: 2, category_id: 2, product_name: 'Desk', unit_price: '150.00' },
]);

// Stubs only the innermost `Modal.vue`, not `DialogModal.vue` - jsdom in
// this project does not implement `HTMLDialogElement.prototype.showModal`.
// See Products/Partials/Form.spec.js for the established precedent.
const mountForm = (props = {}) => mount(Form, {
    props: {
        show: true,
        customers: makeCustomers(),
        products: makeProducts(),
        ...props,
    },
    global: {
        mocks: { $t: (key) => key },
        stubs: {
            Modal: { template: '<div><slot /></div>' },
        },
    },
});

describe('Orders/Partials/Form.vue', () => {
    beforeEach(() => {
        postMock.mockClear();
        resetMock.mockClear();
        clearErrorsMock.mockClear();
        useFormMock.mockReset();
        useFormMock.mockImplementation(fakeUseForm);
    });

    it('always initializes useForm with blank customer_id/product_id and a quantity of 1 - create-only, no edit branch to pre-fill from', () => {
        mountForm();

        expect(useFormMock).toHaveBeenCalledWith({ customer_id: '', product_id: '', quantity: '1' });
    });

    it('resyncs to the first customer, first product, and a quantity of 1 whenever "show" transitions from false to true', async () => {
        const wrapper = mountForm({ show: false });

        await wrapper.setProps({ show: true });
        await nextTick();

        expect(wrapper.find('select#customer_id').element.value).toBe('1');
        expect(wrapper.find('select#product_id').element.value).toBe('1');
        expect(wrapper.find('input#quantity').element.value).toBe('1');
    });

    it('renders one option per customer in the customer select, showing first and last name', () => {
        const wrapper = mountForm();

        const options = wrapper.find('select#customer_id').findAll('option');
        expect(options).toHaveLength(2);
        expect(options[0].text()).toBe('Jane Doe');
        expect(options[1].text()).toBe('John Smith');
    });

    it('renders one option per product in the product select, showing the product name', () => {
        const wrapper = mountForm();

        const options = wrapper.find('select#product_id').findAll('option');
        expect(options).toHaveLength(2);
        expect(options[0].text()).toBe('Laptop');
        expect(options[1].text()).toBe('Desk');
    });

    it('always shows the "Create Order" title - there is no edit mode', () => {
        const wrapper = mountForm();

        expect(wrapper.text()).toContain('Create Order');
    });

    it('posts to OrderController.store on submit', async () => {
        const wrapper = mountForm({ show: true });

        await wrapper.find('select#customer_id').setValue('2');
        await wrapper.find('select#product_id').setValue('2');
        await wrapper.find('input#quantity').setValue('4');
        const submitButton = wrapper.findAll('button').find((button) => button.text() === 'Create');
        await submitButton.trigger('click');

        expect(postMock).toHaveBeenCalledTimes(1);
        expect(postMock).toHaveBeenCalledWith('/orders', expect.objectContaining({ preserveScroll: true }));
    });

    it('renders server-side validation errors passed in via form.errors for customer_id, product_id, and quantity', () => {
        useFormMock.mockImplementation((initial) => {
            const form = fakeUseForm(initial);
            form.errors.customer_id = 'The selected customer id is invalid.';
            form.errors.product_id = 'The selected product id is invalid.';
            form.errors.quantity = 'The quantity must be at least 1.';
            return form;
        });

        const wrapper = mountForm();

        expect(wrapper.text()).toContain('The selected customer id is invalid.');
        expect(wrapper.text()).toContain('The selected product id is invalid.');
        expect(wrapper.text()).toContain('The quantity must be at least 1.');
    });

    it('resets and clears errors, then emits close, when Cancel is clicked', async () => {
        const wrapper = mountForm();

        const cancelButton = wrapper.findAll('button').find((button) => button.text() === 'Cancel');
        await cancelButton.trigger('click');

        expect(resetMock).toHaveBeenCalledTimes(1);
        expect(clearErrorsMock).toHaveBeenCalledTimes(1);
        expect(wrapper.emitted('close')).toHaveLength(1);
    });
});
