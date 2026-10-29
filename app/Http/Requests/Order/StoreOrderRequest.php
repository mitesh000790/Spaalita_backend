<?php

namespace App\Http\Requests\Order;

use App\Enums\PaymentMethod;
use App\Http\Requests\ApiFormRequest;
use App\Rules\NonEmptyList;
use App\Rules\Truthy;
use App\Support\Js;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreOrderRequest extends ApiFormRequest
{
    private const ITEMS_MESSAGE = 'Order items and total amount are required';

    public function rules(): array
    {
        return [
            'items' => [new NonEmptyList(self::ITEMS_MESSAGE)],
            'totalAmount' => [new Truthy(self::ITEMS_MESSAGE)],
            // Optional: "card" (default) or "klarna"
            'paymentMethod' => ['nullable', Rule::enum(PaymentMethod::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'paymentMethod' => 'Invalid payment method',
        ];
    }

    /**
     * Guests (no valid token) must send guestInfo.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                if (! Js::truthy($this->user()?->id) && ! Js::truthy($this->input('guestInfo'))) {
                    $validator->errors()->add('guestInfo', 'Guest information required');
                }
            },
        ];
    }
}
