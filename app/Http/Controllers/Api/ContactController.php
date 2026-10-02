<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\MapsRequestFields;
use App\Http\Controllers\Controller;
use App\Http\Requests\Contact\StoreContactRequest;
use App\Http\Resources\ContactResource;
use App\Models\Contact;
use Illuminate\Http\JsonResponse;
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
