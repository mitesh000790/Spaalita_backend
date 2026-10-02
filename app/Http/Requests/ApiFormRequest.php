<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Base request for API endpoints.
 *
 * Validation failures produce the API's error format:
 * 400 {"success": false, "message": "..."}. The message is $failureMessage
 * when set, otherwise the message of the first failing rule.
 */
abstract class ApiFormRequest extends FormRequest
{
    /**
     * @var bool
     */
    protected $stopOnFirstFailure = true;

    protected ?string $failureMessage = null;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validate the request body only (never the query string).
     *
     * @return array<string, mixed>
     */
    public function validationData(): array
    {
        return $this->getInputSource()->all();
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json(
            $this->failurePayload($validator),
            400,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    protected function failurePayload(Validator $validator): array
    {
        return [
            'success' => false,
            'message' => $this->failureMessage ?? $validator->errors()->first(),
        ];
    }
}
