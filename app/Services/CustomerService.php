<?php

namespace App\Services;

use App\Models\Customer;

/**
 * CRUD for customers. Like `CategoryService`/`ProductService`, this is not
 * one of the RBAC services and does not use `LogsAuditEvents` - audit
 * logging in this project is scoped to RBAC mutations only. No
 * referential-integrity check on delete: nothing references `Customer` on
 * this branch yet - `feature/orders` will add that check the same way
 * `feature/products` added one to `CategoryService::deleteCategory()`.
 */
class CustomerService
{
    public function createCustomer(array $data): Customer
    {
        return Customer::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'telephone' => $data['telephone'],
            'email' => $data['email'],
            'address' => $data['address'],
        ]);
    }

    public function updateCustomer(Customer $customer, array $data): Customer
    {
        $customer->update([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'telephone' => $data['telephone'],
            'email' => $data['email'],
            'address' => $data['address'],
        ]);

        return $customer;
    }

    public function deleteCustomer(Customer $customer): void
    {
        $customer->delete();
    }
}
