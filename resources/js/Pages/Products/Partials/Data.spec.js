import { describe, expect, it, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';

const { routerGetMock, routerDeleteMock, canMock } = vi.hoisted(() => ({
    routerGetMock: vi.fn(),
    routerDeleteMock: vi.fn(),
    canMock: vi.fn(() => true),
}));

vi.mock('@inertiajs/vue3', () => ({
    Link: {
        props: ['href'],
        template: '<a :href="href"><slot /></a>',
    },
    router: {
        get: routerGetMock,
        delete: routerDeleteMock,
    },
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

import Data from './Data.vue';

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
    links: [
        { url: null, label: '&laquo; Previous', active: false },
        { url: '/products?page=1', label: '1', active: true },
        { url: '/products?page=2', label: '2', active: false },
        { url: null, label: 'Next &raquo;', active: false },
    ],
    current_page: 1,
    last_page: 2,
    per_page: 10,
    total: 7,
    from: 1,
    to: 2,
    ...overrides,
});

const mountData = (props = {}) => mount(Data, {
    props: {
        products: makeProducts(),
        categories: makeCategories(),
        search: '',
        category: null,
        ...props,
    },
    global: {
        mocks: { $t: (key) => key },
    },
});

describe('Products/Partials/Data.vue', () => {
    beforeEach(() => {
        routerGetMock.mockClear();
        routerDeleteMock.mockClear();
        canMock.mockReset();
        canMock.mockReturnValue(true);
        vi.spyOn(window, 'confirm').mockReturnValue(true);
    });

    it('renders one row per product with its name, category, and price', () => {
        const wrapper = mountData();

        const rows = wrapper.findAll('tbody tr');
        expect(rows).toHaveLength(2);
        expect(rows[0].text()).toContain('Laptop');
        expect(rows[0].text()).toContain('Electronics');
        expect(rows[0].text()).toContain('999.99');
        expect(rows[1].text()).toContain('Desk');
        expect(rows[1].text()).toContain('Furniture');
    });

    it('renders an empty-state row when there are no products', () => {
        const wrapper = mountData({ products: makeProducts({ data: [] }) });

        expect(wrapper.text()).toContain('No products found.');
    });

    it('renders one option per category plus an "all categories" option in the filter dropdown', () => {
        const wrapper = mountData();

        const options = wrapper.find('select').findAll('option');
        expect(options).toHaveLength(3);
        expect(options[0].text()).toBe('All categories');
        expect(options[1].text()).toBe('Electronics');
        expect(options[2].text()).toBe('Furniture');
    });

    it('emits "create" when the create-product button is clicked', async () => {
        const wrapper = mountData();

        const createButton = wrapper.findAll('button').find((button) => button.text() === 'Create Product');
        await createButton.trigger('click');

        expect(wrapper.emitted('create')).toHaveLength(1);
    });

    it('emits "edit" with the exact product payload when a row\'s edit action is clicked', async () => {
        const wrapper = mountData();

        const editButtons = wrapper.findAll('button').filter((button) => button.text() === 'Edit');
        await editButtons[1].trigger('click');

        expect(wrapper.emitted('edit')).toHaveLength(1);
        expect(wrapper.emitted('edit')[0]).toEqual([makeProducts().data[1]]);
    });

    it('runs a search against ProductController.index with the current search text on enter', async () => {
        const wrapper = mountData({ search: 'lap' });

        const input = wrapper.find('input[type="search"]');
        expect(input.element.value).toBe('lap');

        await input.setValue('laptop');
        await input.trigger('keyup.enter');

        expect(routerGetMock).toHaveBeenCalledTimes(1);
        expect(routerGetMock).toHaveBeenCalledWith('/products', { search: 'laptop', category: undefined }, {
            preserveState: true,
            replace: true,
        });
    });

    it('also runs a search on blur', async () => {
        const wrapper = mountData();

        const input = wrapper.find('input[type="search"]');
        await input.setValue('desk');
        await input.trigger('blur');

        expect(routerGetMock).toHaveBeenCalledWith('/products', { search: 'desk', category: undefined }, {
            preserveState: true,
            replace: true,
        });
    });

    it('selecting a category in the filter dropdown navigates with the chosen category id', async () => {
        const wrapper = mountData();

        const select = wrapper.find('select');
        await select.setValue('2');

        expect(routerGetMock).toHaveBeenCalledTimes(1);
        // Vue's v-model on a <select> whose <option> :value bindings are
        // numbers (cat.id) coerces the bound ref to the matching option's
        // actual JS value (a number), not the raw string the DOM element's
        // .value getter would report - so this arrives as 2, not '2'.
        expect(routerGetMock).toHaveBeenCalledWith('/products', { search: '', category: 2 }, {
            preserveState: true,
            replace: true,
        });
    });

    it('pre-selects the current category filter from the "category" prop', () => {
        const wrapper = mountData({ category: 2 });

        const select = wrapper.find('select');
        expect(select.element.value).toBe('2');
    });

    it('switching the category filter back to "All categories" navigates without a category param', async () => {
        const wrapper = mountData({ category: 2 });

        const select = wrapper.find('select');
        await select.setValue('');

        expect(routerGetMock).toHaveBeenCalledWith('/products', { search: '', category: undefined }, {
            preserveState: true,
            replace: true,
        });
    });

    it('deletes a product via router.delete only after the user confirms', async () => {
        const wrapper = mountData();

        const deleteButtons = wrapper.findAll('button').filter((button) => button.text() === 'Delete');
        await deleteButtons[0].trigger('click');

        expect(window.confirm).toHaveBeenCalledTimes(1);
        expect(routerDeleteMock).toHaveBeenCalledTimes(1);
        expect(routerDeleteMock).toHaveBeenCalledWith('/products/1', { preserveScroll: true });
    });

    it('does not call router.delete when the confirmation is dismissed', async () => {
        window.confirm.mockReturnValue(false);
        const wrapper = mountData();

        const deleteButtons = wrapper.findAll('button').filter((button) => button.text() === 'Delete');
        await deleteButtons[0].trigger('click');

        expect(routerDeleteMock).not.toHaveBeenCalled();
    });

    it('renders a clickable link for pagination entries that have a url, and plain text for the ones that do not', () => {
        const wrapper = mountData();

        const pagination = wrapper.find('.mt-4');
        const links = pagination.findAll('a');
        expect(links).toHaveLength(2);
        expect(wrapper.text()).toContain('Previous');
        expect(wrapper.text()).toContain('Next');
    });

    it('hides every write action (create, edit, delete) when the user lacks PRODUCT:WRITE', () => {
        canMock.mockReturnValue(false);
        const wrapper = mountData();

        expect(wrapper.text()).not.toContain('Create Product');
        expect(wrapper.findAll('button').filter((button) => button.text() === 'Edit')).toHaveLength(0);
        expect(wrapper.findAll('button').filter((button) => button.text() === 'Delete')).toHaveLength(0);
    });
});
