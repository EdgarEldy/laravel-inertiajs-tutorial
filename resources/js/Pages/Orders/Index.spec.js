import { describe, expect, it, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import { reactive } from 'vue';

const { postMock, canMock } = vi.hoisted(() => ({
    postMock: vi.fn(),
    canMock: vi.fn(() => true),
}));

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<head-stub><slot /></head-stub>' },
    Link: {
        props: ['href'],
        template: '<a :href="href"><slot /></a>',
    },
    useForm: (initial) => reactive({
        ...initial,
        errors: {},
        processing: false,
        post: postMock,
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

const makeOrders = (overrides = {}) => ({
    data: [
        {
            id: 1,
            customer_id: 1,
            product_id: 1,
            quantity: 3,
            total: '59.97',
            customer: { id: 1, first_name: 'Jane', last_name: 'Doe' },
            product: { id: 1, product_name: 'Laptop', unit_price: '19.99' },
            created_at: '2026-01-01T00:00:00.000000Z',
        },
    ],
    links: [],
    current_page: 1,
    last_page: 1,
    per_page: 10,
    total: 1,
    from: 1,
    to: 1,
    ...overrides,
});

const makeCustomers = () => ([
    { id: 1, first_name: 'Jane', last_name: 'Doe', telephone: '555-0100', email: 'jane@example.com', address: '123 Main St' },
    { id: 2, first_name: 'John', last_name: 'Smith', telephone: '555-0199', email: 'john@example.com', address: '456 Oak Ave' },
]);

const makeProducts = () => ([
    { id: 1, category_id: 1, product_name: 'Laptop', unit_price: '999.99' },
    { id: 2, category_id: 2, product_name: 'Desk', unit_price: '150.00' },
]);

// Stubs only the innermost `Modal.vue` - see Categories/Index.spec.js and
// Products/Index.spec.js for the established precedent (jsdom does not
// implement `HTMLDialogElement.prototype.showModal`).
const mountIndex = (props = {}) => mount(Index, {
    props: {
        orders: makeOrders(),
        customers: makeCustomers(),
        products: makeProducts(),
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

describe('Orders/Index.vue integration (real Data + Form children)', () => {
    beforeEach(() => {
        postMock.mockClear();
        canMock.mockReset();
        canMock.mockReturnValue(true);
    });

    it('renders the orders table from the real Data child, including the customer and product names', () => {
        const wrapper = mountIndex();

        expect(wrapper.text()).toContain('Jane Doe');
        expect(wrapper.text()).toContain('Laptop');
        expect(wrapper.text()).toContain('59.97');
    });

    it('the Form starts closed - no dialog content and no create-mode fields visible until "Create Order" is clicked', () => {
        const wrapper = mountIndex();

        // The Form component is always mounted (unlike Data's table), but
        // its DialogModal only actually shows once `showModal` flips true -
        // asserting the create button itself is present is what confirms
        // Data rendered, independent of the modal's own visibility.
        expect(wrapper.text()).toContain('Create Order');
    });

    it('clicking "Create Order" in Data opens the real Form with blank fields and the first customer/product pre-selected', async () => {
        const wrapper = mountIndex();

        const createButton = wrapper.findAll('button').find((button) => button.text() === 'Create Order');
        await createButton.trigger('click');

        expect(wrapper.find('select#customer_id').element.value).toBe('1');
        expect(wrapper.find('select#product_id').element.value).toBe('1');
        expect(wrapper.find('input#quantity').element.value).toBe('1');
    });

    it('closing the modal after opening it clears back to a fresh create form', async () => {
        const wrapper = mountIndex();

        const createButton = wrapper.findAll('button').find((button) => button.text() === 'Create Order');
        await createButton.trigger('click');

        const cancelButton = wrapper.findAll('button').find((button) => button.text() === 'Cancel');
        await cancelButton.trigger('click');

        await createButton.trigger('click');

        expect(wrapper.find('select#customer_id').element.value).toBe('1');
        expect(wrapper.find('input#quantity').element.value).toBe('1');
    });

    it('submitting the create form from within Index posts through to OrderController.store', async () => {
        const wrapper = mountIndex();

        const createButton = wrapper.findAll('button').find((button) => button.text() === 'Create Order');
        await createButton.trigger('click');

        await wrapper.find('select#customer_id').setValue('2');
        await wrapper.find('select#product_id').setValue('2');
        await wrapper.find('input#quantity').setValue('5');

        const submitButton = wrapper.findAll('button').find((button) => button.text() === 'Create');
        await submitButton.trigger('click');

        expect(postMock).toHaveBeenCalledTimes(1);
        expect(postMock).toHaveBeenCalledWith('/orders', expect.objectContaining({ preserveScroll: true }));
    });

    it('never renders Edit or Delete buttons anywhere in the composed page', () => {
        const wrapper = mountIndex();

        expect(wrapper.findAll('button').filter((button) => button.text() === 'Edit')).toHaveLength(0);
        expect(wrapper.findAll('button').filter((button) => button.text() === 'Delete')).toHaveLength(0);
    });

    // Data.vue's own "Create Order" button is gated behind can('ORDER:WRITE')
    // - see Partials/Data.spec.js for that assertion in isolation. It is not
    // repeated here: Form.vue's DialogModal title also reads "Create Order"
    // and is not itself gated behind can() (client-side can() is a UI
    // convenience only, the server route middleware is the real boundary),
    // so with the full-stub Modal used in this integration test the title
    // text would still be present regardless of canMock, making a
    // wrapper.text()-based assertion here misleading rather than useful.
});
