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

const makeCustomers = (overrides = {}) => ({
    data: [
        { id: 1, first_name: 'Jane', last_name: 'Doe', telephone: '555-0100', email: 'jane@example.com', address: '123 Main St', created_at: '2026-01-01T00:00:00.000000Z' },
        { id: 2, first_name: 'John', last_name: 'Smith', telephone: '555-0199', email: 'john@example.com', address: '456 Oak Ave', created_at: '2026-01-02T00:00:00.000000Z' },
    ],
    links: [
        { url: null, label: '&laquo; Previous', active: false },
        { url: '/customers?page=1', label: '1', active: true },
        { url: '/customers?page=2', label: '2', active: false },
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
        customers: makeCustomers(),
        search: '',
        ...props,
    },
    global: {
        mocks: { $t: (key) => key },
    },
});

describe('Customers/Partials/Data.vue', () => {
    beforeEach(() => {
        routerGetMock.mockClear();
        routerDeleteMock.mockClear();
        canMock.mockReset();
        canMock.mockReturnValue(true);
        vi.spyOn(window, 'confirm').mockReturnValue(true);
    });

    it('renders one row per customer with its name, telephone, email, and address', () => {
        const wrapper = mountData();

        const rows = wrapper.findAll('tbody tr');
        expect(rows).toHaveLength(2);
        expect(rows[0].text()).toContain('Jane');
        expect(rows[0].text()).toContain('Doe');
        expect(rows[0].text()).toContain('555-0100');
        expect(rows[0].text()).toContain('jane@example.com');
        expect(rows[0].text()).toContain('123 Main St');
        expect(rows[1].text()).toContain('John');
        expect(rows[1].text()).toContain('Smith');
    });

    it('renders an empty-state row when there are no customers', () => {
        const wrapper = mountData({ customers: makeCustomers({ data: [] }) });

        expect(wrapper.text()).toContain('No customers found.');
    });

    it('emits "create" when the create-customer button is clicked', async () => {
        const wrapper = mountData();

        await wrapper.find('button').trigger('click');

        expect(wrapper.emitted('create')).toHaveLength(1);
    });

    it('emits "edit" with the exact customer payload when a row\'s edit action is clicked', async () => {
        const wrapper = mountData();

        const editButtons = wrapper.findAll('button').filter((button) => button.text() === 'Edit');
        await editButtons[1].trigger('click');

        expect(wrapper.emitted('edit')).toHaveLength(1);
        expect(wrapper.emitted('edit')[0]).toEqual([
            { id: 2, first_name: 'John', last_name: 'Smith', telephone: '555-0199', email: 'john@example.com', address: '456 Oak Ave', created_at: '2026-01-02T00:00:00.000000Z' },
        ]);
    });

    it('runs a search against CustomerController.index with the current search text on enter', async () => {
        const wrapper = mountData({ search: 'jan' });

        const input = wrapper.find('input[type="search"]');
        expect(input.element.value).toBe('jan');

        await input.setValue('jane');
        await input.trigger('keyup.enter');

        expect(routerGetMock).toHaveBeenCalledTimes(1);
        expect(routerGetMock).toHaveBeenCalledWith('/customers', { search: 'jane' }, {
            preserveState: true,
            replace: true,
        });
    });

    it('also runs a search on blur', async () => {
        const wrapper = mountData();

        const input = wrapper.find('input[type="search"]');
        await input.setValue('smith');
        await input.trigger('blur');

        expect(routerGetMock).toHaveBeenCalledWith('/customers', { search: 'smith' }, {
            preserveState: true,
            replace: true,
        });
    });

    it('deletes a customer via router.delete only after the user confirms', async () => {
        const wrapper = mountData();

        const deleteButtons = wrapper.findAll('button').filter((button) => button.text() === 'Delete');
        await deleteButtons[0].trigger('click');

        expect(window.confirm).toHaveBeenCalledTimes(1);
        expect(routerDeleteMock).toHaveBeenCalledTimes(1);
        expect(routerDeleteMock).toHaveBeenCalledWith('/customers/1', { preserveScroll: true });
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

    it('hides every write action (create, edit, delete) when the user lacks CUSTOMER:WRITE', () => {
        canMock.mockReturnValue(false);
        const wrapper = mountData();

        expect(wrapper.text()).not.toContain('Create Customer');
        expect(wrapper.findAll('button').filter((button) => button.text() === 'Edit')).toHaveLength(0);
        expect(wrapper.findAll('button').filter((button) => button.text() === 'Delete')).toHaveLength(0);
    });
});
