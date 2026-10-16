<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Catalog\StoreGiftCardRequest;
use App\Models\GiftCard;
use Illuminate\Http\JsonResponse;

/**
 * /api/giftcards
 */
class GiftCardController extends CatalogItemController
{
    /**
     * POST /api/giftcards (description is optional)
     */
    public function store(StoreGiftCardRequest $request): JsonResponse
    {
        return $this->createItem($request);
    }

    protected function model(): string
    {
        return GiftCard::class;
    }

    protected function messages(): array
    {
        return [
            'created' => 'GiftCard created successfully',
            'createFailed' => 'Error creating GiftCard',
            'notFound' => 'GiftCard not found',
            'updated' => 'GiftCard updated successfully',
            'updateFailed' => 'Error updating giftcard',
            'listFailed' => 'Error fetching giftCard',
            'showFailed' => 'Error fetching GiftCard details',
            'deleteNotFound' => 'Gift card not found',
            'deleted' => 'Gift Card deleted (status set to inactive)',
            'deleteFailed' => 'Error deleting gift card',
            'relatedNotFound' => 'Gift card not found',
            'relatedFailed' => 'Error fetching related gift cards',
        ];
    }
}
