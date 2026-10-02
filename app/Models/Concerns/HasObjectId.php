<?php

namespace App\Models\Concerns;

use App\Models\Builders\ObjectIdBuilder;
use App\Support\ObjectId;
use Illuminate\Database\Eloquent\Concerns\HasUniqueStringIds;
use Illuminate\Database\Query\Builder;

/**
 * Gives a model a 24-character MongoDB-style ObjectId primary key,
 * generated automatically when the model is created.
 */
trait HasObjectId
{
    use HasUniqueStringIds;

    public function newUniqueId(): string
    {
        return ObjectId::generate();
    }

    protected function isValidUniqueId($value): bool
    {
        return ObjectId::isValid($value);
    }

    /**
     * @param  Builder  $query
     */
    public function newEloquentBuilder($query): ObjectIdBuilder
    {
        return new ObjectIdBuilder($query);
    }
}
