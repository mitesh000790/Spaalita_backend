<?php

namespace App\Models;

use App\Models\Concerns\HasObjectId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An order line. Each line keeps its own ObjectId (`_id`), as the embedded
 * MongoDB sub-documents did.
 */
class OrderItem extends Model
{
    use HasObjectId;

    public $timestamps = false;

    protected $fillable = [
        'product_id',
        'name',
        'price',
        'qty',
        'image',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'float',
            'qty' => 'integer',
            'position' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * The referenced product. Not a foreign key: the id is stored as sent by
     * the client and may also point at a gift card.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
