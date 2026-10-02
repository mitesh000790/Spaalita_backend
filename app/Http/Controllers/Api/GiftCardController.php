<?php

namespace App\Http\Controllers\Api;

use App\Models\GiftCard;

/**
 * /api/giftcards
 */
class GiftCardController extends CatalogItemController
{
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
