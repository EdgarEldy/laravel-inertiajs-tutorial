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

const customerFixture = {
    id: 5,
    first_name: 'Jane',
    last_name: 'Doe',
    telephone: '555-0100',
    email: 'jane@example.com',
    address: '123 Main St',
};

// Stubs only the innermost `Modal.vue` (a real <dialog> element driven by
// `dialog.showModal()`/`dialog.close()`), not `DialogModal.vue` - jsdom in
// this project does not implement `HTMLDialogElement.prototype.showModal`,
// so exercising it here would fail on a test-environment gap, not an
// application bug. See Categories/Partials/Form.spec.js for the established
// precedent of this exact stub.
const mountForm = (props = {}) => mount(Form, {
    props: {
        show: true,
        customer: null,
        ...props,
    },
    global: {
        mocks: { $t: (key) => key },
        stubs: {
            Modal: { template: '<div><slot /></div>' },
        },
    },
});

describe('Customers/Partials/Form.vue', () => {
    beforeEach(() => {
        postMock.mockClear();
        putMock.mockClear();
        resetMock.mockClear();
        clearErrorsMock.mockClear();
        useFormMock.mockReset();
        useFormMock.mockImplementation(fakeUseForm);
    });

    it('always initializes useForm with blank fields, regardless of the customer prop (fields are synced later via a watch on "show", not at construction time)', () => {
        mountForm({ show: false, customer: customerFixture });

        expect(useFormMock).toHaveBeenCalledWith({
            first_name: '',
            last_name: '',
            telephone: '',
            email: '',
            address: '',
        });
    });

    it('does NOT pre-fill fields just from being mounted with show already true - the sync only happens on a false-to-true transition of "show"', () => {
        const wrapper = mountForm({ show: true, customer: customerFixture });

        expect(wrapper.find('input#first_name').element.value).toBe('');
        expect(wrapper.find('input#last_name').element.value).toBe('');
        expect(wrapper.find('input#telephone').element.value).toBe('');
        expect(wrapper.find('input#email').element.value).toBe('');
        expect(wrapper.find('input#address').element.value).toBe('');
    });

    it('pre-fills all five fields from the customer being edited once "show" transitions from false to true', async () => {
        const wrapper = mountForm({ show: false, customer: customerFixture });

        await wrapper.setProps({ show: true });
        await nextTick();

        expect(wrapper.find('input#first_name').element.value).toBe('Jane');
        expect(wrapper.find('input#last_name').element.value).toBe('Doe');
        expect(wrapper.find('input#telephone').element.value).toBe('555-0100');
        expect(wrapper.find('input#email').element.value).toBe('jane@example.com');
        expect(wrapper.find('input#address').element.value).toBe('123 Main St');
    });

    it('resyncs to blank fields when "show" transitions from false to true with customer null (create mode)', async () => {
        const wrapper = mountForm({ show: false, customer: null });

        await wrapper.setProps({ show: true });
        await nextTick();

        expect(wrapper.find('input#first_name').element.value).toBe('');
        expect(wrapper.find('input#email').element.value).toBe('');
    });

    it('shows the create title/button when customer is null, the edit title/button otherwise', () => {
        const createWrapper = mountForm({ customer: null });
        expect(createWrapper.text()).toContain('Create Customer');

        const editWrapper = mountForm({ customer: customerFixture });
        expect(editWrapper.text()).toContain('Edit Customer');
    });

    it('posts to CustomerController.store on create', async () => {
        const wrapper = mountForm({ show: true, customer: null });

        await wrapper.find('input#first_name').setValue('Jane');
        await wrapper.find('input#last_name').setValue('Doe');
        await wrapper.find('input#telephone').setValue('555-0100');
        await wrapper.find('input#email').setValue('jane@example.com');
        await wrapper.find('input#address').setValue('123 Main St');
        const submitButton = wrapper.findAll('button').find((button) => button.text() === 'Create');
        await submitButton.trigger('click');

        expect(postMock).toHaveBeenCalledTimes(1);
        expect(postMock).toHaveBeenCalledWith('/customers', expect.objectContaining({ preserveScroll: true }));
        expect(putMock).not.toHaveBeenCalled();
    });

    it('puts to CustomerController.update on edit', async () => {
        const wrapper = mountForm({ show: false, customer: customerFixture });
        await wrapper.setProps({ show: true });
        await nextTick();

        const submitButton = wrapper.findAll('button').find((button) => button.text() === 'Save');
        await submitButton.trigger('click');

        expect(putMock).toHaveBeenCalledTimes(1);
        expect(putMock).toHaveBeenCalledWith('/customers/5', expect.objectContaining({ preserveScroll: true }));
        expect(postMock).not.toHaveBeenCalled();
    });

    it('renders a server-side validation error passed in via form.errors', async () => {
        useFormMock.mockImplementation((initial) => {
            const form = fakeUseForm(initial);
            form.errors.email = 'The email has already been taken.';
            return form;
        });

        const wrapper = mountForm({ customer: null });

        expect(wrapper.text()).toContain('The email has already been taken.');
    });

    it('renders server-side validation errors for each of the five fields', async () => {
        useFormMock.mockImplementation((initial) => {
            const form = fakeUseForm(initial);
            form.errors.first_name = 'The first name field is required.';
            form.errors.last_name = 'The last name field is required.';
            form.errors.telephone = 'The telephone field is required.';
            form.errors.address = 'The address field is required.';
            return form;
        });

        const wrapper = mountForm({ customer: null });

        expect(wrapper.text()).toContain('The first name field is required.');
        expect(wrapper.text()).toContain('The last name field is required.');
        expect(wrapper.text()).toContain('The telephone field is required.');
        expect(wrapper.text()).toContain('The address field is required.');
    });

    it('resets and clears errors, then emits close, when Cancel is clicked', async () => {
        const wrapper = mountForm({ customer: null });

        const cancelButton = wrapper.findAll('button').find((button) => button.text() === 'Cancel');
        await cancelButton.trigger('click');

        expect(resetMock).toHaveBeenCalledTimes(1);
        expect(clearErrorsMock).toHaveBeenCalledTimes(1);
        expect(wrapper.emitted('close')).toHaveLength(1);
    });
});
