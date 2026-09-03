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
