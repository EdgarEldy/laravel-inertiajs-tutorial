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

const makeCategories = () => ([
    { id: 1, category_name: 'Electronics', created_at: '2026-01-01T00:00:00.000000Z' },
    { id: 2, category_name: 'Furniture', created_at: '2026-01-02T00:00:00.000000Z' },
]);

const makeProducts = (overrides = {}) => ({
    data: [
        {
            id: 1,
            category_id: 1,
            product_name: 'Laptop',
            unit_price: '999.99',
            category: { id: 1, category_name: 'Electronics', created_at: '2026-01-01T00:00:00.000000Z' },
            created_at: '2026-01-01T00:00:00.000000Z',
        },
        {
            id: 2,
            category_id: 2,
            product_name: 'Desk',
            unit_price: '150.00',
            category: { id: 2, category_name: 'Furniture', created_at: '2026-01-02T00:00:00.000000Z' },
            created_at: '2026-01-02T00:00:00.000000Z',
        },
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
// on a test-environment gap, not an application bug. See
// Categories/Index.spec.js for the established precedent of this exact stub.
const mountIndex = (props = {}) => mount(Index, {
    props: {
        products: makeProducts(),
        categories: makeCategories(),
        filters: { search: '', category: null },
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

describe('Products/Index.vue integration (real Data + Form children)', () => {
    beforeEach(() => {
        routerGetMock.mockClear();
        routerDeleteMock.mockClear();
        postMock.mockClear();
        putMock.mockClear();
        canMock.mockReset();
        canMock.mockReturnValue(true);
    });

    it('renders the products table from the real Data child', () => {
        const wrapper = mountIndex();

        expect(wrapper.text()).toContain('Laptop');
        expect(wrapper.text()).toContain('Desk');
        expect(wrapper.text()).toContain('Electronics');
        expect(wrapper.text()).toContain('Furniture');
    });

    it('the Form starts in create mode (blank fields, first category selected) before any row is opened for edit', () => {
        const wrapper = mountIndex();

        const nameInput = wrapper.find('input#product_name');
        expect(nameInput.exists()).toBe(true);
        expect(nameInput.element.value).toBe('');
        expect(wrapper.text()).toContain('Create Product');
    });

    it('clicking a row\'s Edit action in Data actually opens Form pre-filled with that row\'s data', async () => {
        const wrapper = mountIndex();

        const editButtons = wrapper.findAll('button').filter((button) => button.text() === 'Edit');
        await editButtons[1].trigger('click');
        await nextTick();

        expect(wrapper.find('select#category_id').element.value).toBe('2');
        expect(wrapper.find('input#product_name').element.value).toBe('Desk');
        expect(wrapper.find('input#unit_price').element.value).toBe('150.00');
        expect(wrapper.text()).toContain('Edit Product');
    });

    it('closing the modal after opening it for edit clears editingProduct state back to a blank create form', async () => {
        const wrapper = mountIndex();

        const editButtons = wrapper.findAll('button').filter((button) => button.text() === 'Edit');
        await editButtons[0].trigger('click');
        await nextTick();
        expect(wrapper.find('input#product_name').element.value).toBe('Laptop');

        const cancelButton = wrapper.findAll('button').find((button) => button.text() === 'Cancel');
        await cancelButton.trigger('click');
        await nextTick();

        const createButton = wrapper.findAll('button').find((button) => button.text() === 'Create Product');
        await createButton.trigger('click');
        await nextTick();

        const input = wrapper.find('input#product_name');
        expect(input.element.value).toBe('');
        expect(wrapper.text()).toContain('Create Product');
    });

    it('submitting the edit form from within Index posts through to ProductController.update with the real product id', async () => {
        const wrapper = mountIndex();

        const editButtons = wrapper.findAll('button').filter((button) => button.text() === 'Edit');
        await editButtons[1].trigger('click');
        await nextTick();

        const saveButton = wrapper.findAll('button').find((button) => button.text() === 'Save');
        await saveButton.trigger('click');

        expect(putMock).toHaveBeenCalledTimes(1);
        expect(putMock).toHaveBeenCalledWith('/products/2', expect.objectContaining({ preserveScroll: true }));
    });

    it('selecting a category in the Data child\'s filter dropdown triggers a real navigation request', async () => {
        const wrapper = mountIndex();

        const select = wrapper.find('select');
        await select.setValue('1');

        expect(routerGetMock).toHaveBeenCalledTimes(1);
        // See Products/Partials/Data.spec.js for why this is the number 1,
        // not the string '1'.
        expect(routerGetMock).toHaveBeenCalledWith('/products', { search: '', category: 1 }, {
            preserveState: true,
            replace: true,
        });
    });
});
