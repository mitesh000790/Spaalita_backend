<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\CastException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Order\StoreOrderRequest;
use App\Http\Resources\CreatedOrderResource;
use App\Http\Resources\OrderListResource;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\OrderAddress;
use App\Services\CheckoutService;
use App\Services\StripeService;
use App\Support\Js;
use App\Support\Like;
use App\Support\Pagination;
use App\Support\TypeCaster;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Throwable;

class OrderController extends Controller
{
    private const ADDRESS_FIELDS = [
        'shippingAddress' => OrderAddress::TYPE_SHIPPING,
        'billingAddress' => OrderAddress::TYPE_BILLING,
    ];

    /**
     * GET /api/orders
     *
     * Admins see every order; other users only their own. Orders without a
     * registered customer (guest checkouts) are not listed.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $pagination = Pagination::strict($request);
            $status = $request->query('status');
            $search = (string) $request->query('search', '');
            $authUser = $request->user();

            $query = Order::query()->whereHas('user');

            if (! $authUser->isAdmin()) {
                $query->where('user_id', $authUser->id);
            }

            if (Js::truthy($status)) {
                $query->where('status', $status);
            }

            if (trim($search) !== '') {
                $pattern = Like::contains($search);

                $query->where(fn (Builder $query) => $query
                    ->where('status', 'like', $pattern)
                    ->orWhereHas('user', fn (Builder $user) => $user
                        ->where('user_name', 'like', $pattern)
                        ->orWhere('email', 'like', $pattern))
                    ->orWhereHas('items.product', fn (Builder $product) => $product
                        ->where('product_name', 'like', $pattern)));
            }

            $total = $query->count();

            $orders = $pagination->apply($query->newestFirst())
                ->with(['user.billingAddress', 'user.shippingAddress', 'items', 'shippingAddress', 'billingAddress'])
                ->get();

            return response()->json([
                'success' => true,
                'data' => OrderListResource::collection($orders),
                'pagination' => [
                    'total' => $total,
                    'page' => $pagination->page,
                    'limit' => $pagination->limit,
                    'totalPages' => $pagination->pages($total),
                ],
            ]);
        } catch (Throwable $exception) {
            return $this->serverError('Error fetching orders', $exception);
        }
    }

    /**
     * POST /api/orders - checkout for guests and logged-in users.
     *
     * Returns the order and the Stripe client secret used to confirm payment.
     */
    public function store(StoreOrderRequest $request, CheckoutService $checkout): JsonResponse
    {
        try {
            [$order, $paymentIntent] = $checkout->placeOrder($request->validationData(), $request->user()?->id);

            $order->load(['user', 'items.product', 'shippingAddress', 'billingAddress']);

            return response()->json([
                'success' => true,
                'message' => 'Order created successfully',
                'data' => new CreatedOrderResource($order),
                'clientSecret' => $paymentIntent->client_secret,
            ], 201);
        } catch (Throwable $exception) {
            return $this->serverError('Error creating order', $exception);
        }
    }

    /**
     * PUT /api/orders/{id}/status
     */
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        try {
            $attributes = $request->exists('status')
                ? ['status' => TypeCaster::string($request->input('status'), 'status')]
                : [];

            $order = Order::query()->find($id);

            if ($order === null) {
                return response()->json(['success' => false, 'message' => 'Order not found'], 404);
            }

            $order->applyUpdate($attributes);

            return response()->json([
                'success' => true,
                'message' => 'Order status updated',
                'data' => new OrderResource($order->load(['items', 'shippingAddress', 'billingAddress'])),
            ]);
        } catch (Throwable $exception) {
            return $this->serverError('Error updating order', $exception);
        }
    }

    /**
     * PUT /api/orders/{id}
     *
     * Updatable: status, paymentStatus, billingAddress, shippingAddress
     * (an address sent replaces the stored one; null removes it).
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $attributes = [];

            foreach (['status' => 'status', 'paymentStatus' => 'payment_status'] as $field => $column) {
                if ($request->exists($field)) {
                    $attributes[$column] = TypeCaster::string($request->input($field), $field);
                }
            }

            $addresses = [];

            foreach (self::ADDRESS_FIELDS as $field => $type) {
                if ($request->exists($field)) {
                    $addresses[$type] = $this->addressAttributes($request->input($field), $field);
                }
            }

            $order = Order::query()->find($id);

            if ($order === null) {
                return response()->json(['success' => false, 'message' => 'Order not found'], 404);
            }

            DB::transaction(function () use ($order, $attributes, $addresses) {
                foreach ($addresses as $type => $address) {
                    $order->addresses()->where('type', $type)->delete();

                    if ($address !== null) {
                        $order->addresses()->create(['type' => $type] + $address);
                    }
                }

                $order->applyUpdate($attributes);
            });

            return response()->json([
                'success' => true,
                'message' => 'Order updated',
                'data' => new OrderResource($order->load(['items', 'shippingAddress', 'billingAddress'])),
            ]);
        } catch (Throwable $exception) {
            return $this->serverError('Error updating order', $exception);
        }
    }

    /**
     * DELETE /api/orders/{id}
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $order = Order::query()->find($id);

            if ($order === null) {
                return response()->json(['success' => false, 'message' => 'Order not found'], 404);
            }

            $order->delete();

            return response()->json(['success' => true, 'message' => 'Order deleted successfully']);
        } catch (Throwable $exception) {
            return $this->serverError('Error deleting order', $exception);
        }
    }

    /**
     * POST /api/orders/webhook - Stripe events (verified with STRIPE_WEBHOOK_SECRET).
     *
     * payment_intent.succeeded marks the order paid; payment_intent.payment_failed marks it failed.
     */
    public function stripeWebhook(Request $request, StripeService $stripe): JsonResponse|Response
    {
        try {
            $event = $stripe->constructWebhookEvent($request->getContent(), $request->header('Stripe-Signature'));
        } catch (Throwable $exception) {
            return response('Webhook Error: '.$exception->getMessage(), 400);
        }

        $outcome = match ($event->type) {
            'payment_intent.succeeded' => ['payment_status' => 'succeeded', 'status' => 'paid'],
            'payment_intent.payment_failed' => ['payment_status' => 'failed', 'status' => 'failed'],
            default => null,
        };

        if ($outcome !== null) {
            Order::query()
                ->where('payment_intent_id', $event->data->object->id)
                ->first()
                ?->applyUpdate($outcome);
        }

        return response()->json(['received' => true]);
    }

    /**
     * @return array<string, string>|null
     */
    private function addressAttributes(mixed $address, string $field): ?array
    {
        if ($address === null) {
            return null;
        }

        if (! is_array($address) || ($address !== [] && array_is_list($address))) {
            throw CastException::make('Embedded', $address, $field);
        }

        $attributes = [];

        foreach (OrderAddress::FIELDS as $key => $column) {
            $attributes[$column] = TypeCaster::string($address[$key] ?? null, "{$field}.{$key}") ?? '';
        }

        return $attributes;
    }
}
