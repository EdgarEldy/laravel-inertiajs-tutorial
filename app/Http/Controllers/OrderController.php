<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\OrderResource;
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
            // A user only needs ORDER:READ to reach this page, not
            // CUSTOMER:READ/PRODUCT:READ - the create form's selects only
            // ever display an id and a name, so only those columns are
            // sent, not the full CustomerResource/ProductResource (which
            // would leak telephone/email/address to anyone who can place
            // an order but was never granted access to customer records).
            'customers' => Customer::query()->orderBy('first_name')->orderBy('last_name')->get(['id', 'first_name', 'last_name']),
            'products' => Product::query()->orderBy('product_name')->get(['id', 'product_name']),
        ]);
    }

    public function store(StoreOrderRequest $request, OrderService $orderService): RedirectResponse
    {
        $orderService->placeOrder($request->validated());

        return redirect()->route('orders.index')->with('flash.banner', 'Order placed.');
    }
}
