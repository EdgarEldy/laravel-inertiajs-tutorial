import { describe, expect, it, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';

const { canMock } = vi.hoisted(() => ({
    canMock: vi.fn(() => true),
}));

vi.mock('@inertiajs/vue3', () => ({
    Link: {
        props: ['href'],
        template: '<a :href="href"><slot /></a>',
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
        {
            id: 2,
            customer_id: 2,
            product_id: 2,
            quantity: 1,
            total: '150.00',
            customer: { id: 2, first_name: 'John', last_name: 'Smith' },
            product: { id: 2, product_name: 'Desk', unit_price: '150.00' },
            created_at: '2026-01-02T00:00:00.000000Z',
        },
    ],
    links: [
        { url: null, label: '&laquo; Previous', active: false },
        { url: '/orders?page=1', label: '1', active: true },
        { url: '/orders?page=2', label: '2', active: false },
        { url: null, label: 'Next &raquo;', active: false },
    ],
    current_page: 1,
    last_page: 2,
    per_page: 10,
    total: 11,
    from: 1,
    to: 2,
    ...overrides,
});

const mountData = (props = {}) => mount(Data, {
    props: {
        orders: makeOrders(),
        ...props,
    },
    global: {
        mocks: { $t: (key) => key },
    },
});

describe('Orders/Partials/Data.vue', () => {
    beforeEach(() => {
        canMock.mockReset();
        canMock.mockReturnValue(true);
    });

    it('renders one row per order with the customer name, product name, quantity, and total', () => {
        const wrapper = mountData();

        const rows = wrapper.findAll('tbody tr');
        expect(rows).toHaveLength(2);
        expect(rows[0].text()).toContain('Jane Doe');
        expect(rows[0].text()).toContain('Laptop');
        expect(rows[0].text()).toContain('3');
        expect(rows[0].text()).toContain('59.97');
        expect(rows[1].text()).toContain('John Smith');
        expect(rows[1].text()).toContain('Desk');
        expect(rows[1].text()).toContain('150.00');
    });

    it('renders an empty-state row when there are no orders', () => {
        const wrapper = mountData({ orders: makeOrders({ data: [] }) });

        expect(wrapper.text()).toContain('No orders found.');
    });

    it('never renders Edit or Delete buttons - orders have no update/destroy route', () => {
        const wrapper = mountData();

        expect(wrapper.findAll('button').filter((button) => button.text() === 'Edit')).toHaveLength(0);
        expect(wrapper.findAll('button').filter((button) => button.text() === 'Delete')).toHaveLength(0);
    });

    it('emits "create" when the create-order button is clicked', async () => {
        const wrapper = mountData();

        const createButton = wrapper.findAll('button').find((button) => button.text() === 'Create Order');
        await createButton.trigger('click');

        expect(wrapper.emitted('create')).toHaveLength(1);
    });

    it('hides the create-order button when the user lacks ORDER:WRITE', () => {
        canMock.mockReturnValue(false);
        const wrapper = mountData();

        expect(wrapper.text()).not.toContain('Create Order');
    });

    it('renders a clickable link for pagination entries that have a url, and plain text for the ones that do not', () => {
        const wrapper = mountData();

        const links = wrapper.findAll('a');
        expect(links).toHaveLength(2);
        expect(wrapper.text()).toContain('Previous');
        expect(wrapper.text()).toContain('Next');
    });

    it('does not render the pagination block when there are 3 or fewer links', () => {
        const wrapper = mountData({ orders: makeOrders({ links: [{ url: null, label: '1', active: true }] }) });

        expect(wrapper.findAll('a')).toHaveLength(0);
    });
});
