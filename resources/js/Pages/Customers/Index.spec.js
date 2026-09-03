import { describe, expect, it, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import { nextTick, reactive } from 'vue';

const { routerGetMock, routerDeleteMock, postMock, putMock, canMock } = vi.hoisted(() => ({
    routerGetMock: vi.fn(),
    routerDeleteMock: vi.fn(),
    postMock: vi.fn(),
    putMock: vi.fn(),
    canMock: vi.fn(() => true),
}));

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<head-stub><slot /></head-stub>' },
    Link: {
        props: ['href'],
        template: '<a :href="href"><slot /></a>',
    },
    router: {
        get: routerGetMock,
        delete: routerDeleteMock,
    },
    useForm: (initial) => reactive({
        ...initial,
        errors: {},
        processing: false,
        post: postMock,
        put: putMock,
        reset() {
            Object.keys(initial).forEach((key) => {
                this[key] = initial[key];
            });
        },
        clearErrors() {
            this.errors = {};
        },
    }),
}));

vi.mock('laravel-vue-i18n', () => ({
    trans: (key, replacements = {}) => Object.entries(replacements).reduce(
        (message, [search, value]) => message.replace(`:${search}`, value),
        key,
    ),
}));

vi.mock('@/lib/can', () => ({
    can: canMock,
}));

import Index from './Index.vue';

const makeCustomers = (overrides = {}) => ({
    data: [
        { id: 1, first_name: 'Jane', last_name: 'Doe', telephone: '555-0100', email: 'jane@example.com', address: '123 Main St', created_at: '2026-01-01T00:00:00.000000Z' },
        { id: 2, first_name: 'John', last_name: 'Smith', telephone: '555-0199', email: 'john@example.com', address: '456 Oak Ave', created_at: '2026-01-02T00:00:00.000000Z' },
    ],
    links: [],
    current_page: 1,
    last_page: 1,
    per_page: 10,
    total: 2,
    from: 1,
    to: 2,
    ...overrides,
});

// Stubs only the innermost `Modal.vue` (a real <dialog> element driven by
// `dialog.showModal()`/`dialog.close()`), not `DialogModal.vue` or
// `Form.vue` - jsdom in this project does not implement
// `HTMLDialogElement.prototype.showModal`, so exercising it here would fail
// on a test-environment gap, not an application bug. `DialogModal.vue`'s own
// title/content/footer slots (and everything `Form.vue` puts inside them)
// still render for real through this stub's plain default slot. Because the
// stub is a plain `<div>`, it also does not block clicks on the underlying
// table the way a real native `<dialog>`'s top-layer/backdrop would in a
// browser - see Categories/Index.spec.js for the established precedent.
const mountIndex = (props = {}) => mount(Index, {
    props: {
        customers: makeCustomers(),
        filters: { search: '' },
        ...props,
    },
    global: {
        mocks: { $t: (key) => key },
        stubs: {
            AppLayout: { template: '<div><slot name="header" /><slot /></div>' },
            Modal: { template: '<div><slot /></div>' },
        },
    },
});

describe('Customers/Index.vue integration (real Data + Form children)', () => {
    beforeEach(() => {
        routerGetMock.mockClear();
        routerDeleteMock.mockClear();
        postMock.mockClear();
        putMock.mockClear();
        canMock.mockReset();
        canMock.mockReturnValue(true);
    });

    it('renders the customers table from the real Data child', () => {
        const wrapper = mountIndex();

        expect(wrapper.text()).toContain('Jane');
        expect(wrapper.text()).toContain('Doe');
        expect(wrapper.text()).toContain('John');
        expect(wrapper.text()).toContain('Smith');
    });

    it('the Form starts in create mode (blank fields) before any row is opened for edit', () => {
        const wrapper = mountIndex();

        const input = wrapper.find('input#first_name');
        expect(input.exists()).toBe(true);
        expect(input.element.value).toBe('');
        expect(wrapper.text()).toContain('Create Customer');
    });

    it('clicking a row\'s Edit action in Data actually opens Form pre-filled with that row\'s data', async () => {
        const wrapper = mountIndex();

        const editButtons = wrapper.findAll('button').filter((button) => button.text() === 'Edit');
        await editButtons[1].trigger('click');
        await nextTick();

        expect(wrapper.find('input#first_name').element.value).toBe('John');
        expect(wrapper.find('input#last_name').element.value).toBe('Smith');
        expect(wrapper.find('input#email').element.value).toBe('john@example.com');
        expect(wrapper.text()).toContain('Edit Customer');
    });

    it('closing the modal after opening it for edit clears editingCustomer state back to a blank create form', async () => {
        const wrapper = mountIndex();

        const editButtons = wrapper.findAll('button').filter((button) => button.text() === 'Edit');
        await editButtons[0].trigger('click');
        await nextTick();
        expect(wrapper.find('input#first_name').element.value).toBe('Jane');

        const cancelButton = wrapper.findAll('button').find((button) => button.text() === 'Cancel');
        await cancelButton.trigger('click');
        await nextTick();

        // Re-opening via "Create" afterwards must show blank fields, not the
        // previously-edited customer's data leaking through.
        const createButton = wrapper.findAll('button').find((button) => button.text() === 'Create Customer');
        await createButton.trigger('click');
        await nextTick();

        const input = wrapper.find('input#first_name');
        expect(input.element.value).toBe('');
        expect(wrapper.text()).toContain('Create Customer');
    });

    it('switching from editing one customer directly to another WITHOUT closing the modal in between does NOT resync the fields - this is the real watch-on-"show" mechanism', async () => {
        const wrapper = mountIndex();

        const editButtons = wrapper.findAll('button').filter((button) => button.text() === 'Edit');
        await editButtons[0].trigger('click');
        await nextTick();
        expect(wrapper.find('input#first_name').element.value).toBe('Jane');

        await editButtons[1].trigger('click');
        await nextTick();

        expect(wrapper.find('input#first_name').element.value).toBe('Jane');

        // Closing and reopening for the second row does correctly resync,
        // since that is a real false-to-true transition of `show`.
        const cancelButton = wrapper.findAll('button').find((button) => button.text() === 'Cancel');
        await cancelButton.trigger('click');
        await nextTick();

        await editButtons[1].trigger('click');
        await nextTick();

        expect(wrapper.find('input#first_name').element.value).toBe('John');
    });

    it('submitting the edit form from within Index posts through to CustomerController.update with the real customer id', async () => {
        const wrapper = mountIndex();

        const editButtons = wrapper.findAll('button').filter((button) => button.text() === 'Edit');
        await editButtons[1].trigger('click');
        await nextTick();

        const saveButton = wrapper.findAll('button').find((button) => button.text() === 'Save');
        await saveButton.trigger('click');

        expect(putMock).toHaveBeenCalledTimes(1);
        expect(putMock).toHaveBeenCalledWith('/customers/2', expect.objectContaining({ preserveScroll: true }));
    });
});
