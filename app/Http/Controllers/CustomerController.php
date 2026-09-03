<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Services\CustomerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * `index`/`store`/`update`/`destroy` on customers. No `create`/`edit`
 * routes - the frontend uses a create/edit modal on `Customers/Index.vue`
 * instead, per this project's fixed convention.
 */
class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();

        $customers = Customer::query()
            ->when($search, fn ($query, $search) => $query->where(function ($query) use ($search) {
                $query->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            }))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->paginate(10)
            ->withQueryString();

        // See RoleController::index() for why `through()` is used instead
        // of `CustomerResource::collection()` on a paginator.
        $customers->through(fn (Customer $customer) => (new CustomerResource($customer))->resolve());

        return Inertia::render('Customers/Index', [
            'customers' => $customers,
            'filters' => ['search' => $search],
        ]);
    }

    public function store(StoreCustomerRequest $request, CustomerService $customerService): RedirectResponse
    {
        $customerService->createCustomer($request->validated());

        return redirect()->route('customers.index')->with('flash.banner', 'Customer created.');
    }

    public function update(UpdateCustomerRequest $request, Customer $customer, CustomerService $customerService): RedirectResponse
    {
        $customerService->updateCustomer($customer, $request->validated());

        return redirect()->route('customers.index')->with('flash.banner', 'Customer updated.');
    }

    public function destroy(Customer $customer, CustomerService $customerService): RedirectResponse
    {
        $customerService->deleteCustomer($customer);

        return redirect()->route('customers.index')->with('flash.banner', 'Customer deleted.');
    }
}
