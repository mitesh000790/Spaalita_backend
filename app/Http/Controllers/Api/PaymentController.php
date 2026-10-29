<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\CreatePaymentIntentRequest;
use App\Services\StripeService;
use App\Support\TypeCaster;
use Illuminate\Http\JsonResponse;
use Throwable;

class PaymentController extends Controller
{
    public function __construct(private readonly StripeService $stripe) {}

    /**
     * POST /api/payment/create-payment-intent
     *
     * The amount is in dollars; Stripe receives cents. The currency defaults
     * to the store currency (services.stripe.currency).
     */
    public function createPaymentIntent(CreatePaymentIntentRequest $request): JsonResponse
    {
        try {
            $amount = TypeCaster::number($request->input('amount'), 'amount');
            $currency = $request->input('currency') ?? config('services.stripe.currency');

            $intent = $this->stripe->createPaymentIntent(
                (int) round($amount * 100),
                TypeCaster::string($currency, 'currency') ?? config('services.stripe.currency'),
            );

            return response()->json(['success' => true, 'clientSecret' => $intent->client_secret]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }
}
