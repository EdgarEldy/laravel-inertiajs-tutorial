<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Validation\ValidationException;

/**
 * CRUD for customers. Like `CategoryService`/`ProductService`, this is not
 * one of the RBAC services and does not use `LogsAuditEvents` - audit
 * logging in this project is scoped to RBAC mutations only.
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

    /**
     * Rejects deletion if the customer still has any orders - same
     * referential-integrity pattern as
     * `CategoryService::deleteCategory()`: a `ValidationException`
     * surfaces as a redirect-back field error instead of letting the
     * database's own `restrictOnDelete()` foreign key raise a raw query
     * exception up to the user.
     */
    public function deleteCustomer(Customer $customer): void
    {
        if ($customer->orders()->exists()) {
            throw ValidationException::withMessages([
                'customer' => 'This customer still has orders and cannot be deleted.',
            ]);
        }

        $customer->delete();
    }
}
