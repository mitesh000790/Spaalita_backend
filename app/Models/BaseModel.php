<?php

namespace App\Models;

use App\Models\Concerns\HasObjectId;
use App\Models\Concerns\HasSchemaValidation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Base class for every top-level resource (each one was a MongoDB collection
 * in the original backend): ObjectId primary keys, millisecond-precision
 * timestamps and schema validation on create.
 */
abstract class BaseModel extends Model
{
    use HasObjectId;
    use HasSchemaValidation;

    /**
     * Timestamps keep millisecond precision, matching MongoDB dates.
     *
     * @var string
     */
    protected $dateFormat = 'Y-m-d H:i:s.v';

    /**
     * Newest first; records created at the same moment keep insertion order.
     */
    public function scopeNewestFirst(Builder $query): void
    {
        $query->latest()->orderBy($this->getQualifiedKeyName());
    }

    /**
     * Apply a partial update and save, always refreshing updated_at (even
     * when no value changed), like the MongoDB updates of the original API.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function applyUpdate(array $attributes): static
    {
        $this->fill($attributes);

        if ($this->usesTimestamps()) {
            $this->updateTimestamps();
        }

        $this->save();

        return $this;
    }
}
