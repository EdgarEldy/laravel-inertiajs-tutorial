<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\OrderResource;
use App\Http\Resources\ProductResource;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * `index`/`store` only on orders - there is no `update`/`destroy` route,
 * orders are a create-only resource per this project's fixed convention.
 */
class OrderController extends Controller
{
    public function index(): Response
    {
        $orders = Order::query()
            ->with(['customer', 'product'])
            ->latest()
            ->paginate(10)
            ->withQueryString();

        // See RoleController::index() for why `through()` is used instead
        // of `OrderResource::collection()` on a paginator.
        $orders->through(fn (Order $order) => (new OrderResource($order))->resolve());

        return Inertia::render('Orders/Index', [
            'orders' => $orders,
            'customers' => CustomerResource::collection(Customer::query()->orderBy('first_name')->orderBy('last_name')->get())->resolve(),
            'products' => ProductResource::collection(Product::query()->orderBy('product_name')->get())->resolve(),
        ]);
    }

    public function store(StoreOrderRequest $request, OrderService $orderService): RedirectResponse
    {
        $orderService->placeOrder($request->validated());

        return redirect()->route('orders.index')->with('flash.banner', 'Order placed.');
    }
}
