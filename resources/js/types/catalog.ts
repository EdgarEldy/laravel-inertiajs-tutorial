export interface Category {
    id: number;
    category_name: string;
    created_at: string;
}

export interface Product {
    id: number;
    category_id: number;
    product_name: string;
    unit_price: string;
    category?: Category;
    created_at: string;
}

export interface Customer {
    id: number;
    first_name: string;
    last_name: string;
    telephone: string;
    email: string;
    address: string;
    created_at: string;
}

export interface Order {
    id: number;
    customer_id: number;
    product_id: number;
    quantity: number;
    total: string;
    customer?: Customer;
    product?: Product;
    created_at: string;
}
