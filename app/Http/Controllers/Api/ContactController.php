<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\MapsRequestFields;
use App\Http\Controllers\Controller;
use App\Http\Requests\Contact\StoreContactRequest;
use App\Http\Resources\ContactResource;
use App\Models\Contact;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class ContactController extends Controller
{
    use MapsRequestFields;

    private const FIELDS = [
        'name' => ['name'],
        'email' => ['email'],
        'phone' => ['phone'],
        'address' => ['address'],
        'subject' => ['subject'],
        'message' => ['message'],
    ];

    /**
     * POST /api/contact
     */
    public function store(StoreContactRequest $request): JsonResponse
    {
        $secret = config('services.recaptcha.secret');
        if (! is_string($secret) || $secret === '') {
            Log::error('Contact reCAPTCHA verification is not configured.');

            return response()->json([
                'success' => false,
                'message' => 'Contact form verification is temporarily unavailable.',
            ], 503);
        }

        try {
            $verification = Http::asForm()
                ->connectTimeout(3)
                ->timeout(5)
                ->post('https://www.google.com/recaptcha/api/siteverify', [
                    'secret' => $secret,
                    'response' => $request->string('g-recaptcha-response')->toString(),
                    'remoteip' => $request->ip(),
                ]);
        } catch (ConnectionException $exception) {
            Log::error('Contact reCAPTCHA verification request failed.', [
                'exception' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Contact form verification is temporarily unavailable.',
            ], 503);
        }

        if (! $verification->successful()) {
            Log::error('Contact reCAPTCHA verification returned an unsuccessful HTTP status.', [
                'status' => $verification->status(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Contact form verification is temporarily unavailable.',
            ], 503);
        }

        $score = $verification->json('score');
        if (
            $verification->json('success') !== true
            || $verification->json('action') !== 'contact_submit'
            || ! is_numeric($score)
            || (float) $score < config('services.recaptcha.minimum_score', 0.5)
        ) {
            return response()->json([
                'success' => false,
                'message' => 'reCAPTCHA verification failed. Please try again.',
            ], 422);
        }

        try {
            $contact = Contact::query()->create($this->stringAttributes($request, self::FIELDS));

            return response()->json([
                'success' => true,
                'message' => 'Contact form submitted successfully.',
                'data' => new ContactResource($contact),
            ], 201);
        } catch (Throwable $exception) {
            return $this->serverError('Error submitting contact form', $exception);
        }
    }
}
