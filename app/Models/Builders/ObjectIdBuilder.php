<?php

namespace App\Models\Builders;

use App\Support\ObjectId;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Query builder for models keyed by ObjectIds.
 *
 * Key lookups (find(), whereKey(), whereKeyNot()) ignore values that cannot
 * be ObjectIds, so a malformed id in a URL simply matches nothing (404)
 * instead of reaching the database.
 *
 * @template TModel of Model
 *
 * @extends Builder<TModel>
 */
class ObjectIdBuilder extends Builder
{
    public function whereKey($id)
    {
        return parent::whereKey($this->validIds($id));
    }

    public function whereKeyNot($id)
    {
        return parent::whereKeyNot($this->validIds($id));
    }

    /**
     * @return Model|list<mixed>|string
     */
    private function validIds(mixed $id): mixed
    {
        if ($id instanceof Model) {
            return $id;
        }

        if (is_array($id) || $id instanceof Arrayable) {
            return array_values(array_filter(
                $id instanceof Arrayable ? $id->toArray() : $id,
                fn ($value) => $value instanceof Model || ObjectId::isValid($value),
            ));
        }

        return ObjectId::isValid($id) ? $id : [];
    }
}
