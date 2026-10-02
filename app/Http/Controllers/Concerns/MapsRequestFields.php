<?php

namespace App\Http\Controllers\Concerns;

use App\Support\TypeCaster;
use Illuminate\Http\Request;

trait MapsRequestFields
{
    /**
     * Cast string fields from the request body into column values.
     *
     * With $onlyPresent, fields absent from the request are skipped, so an
     * update only touches what the client sent.
     *
     * @param  array<string, array{0: string, 1?: array<string, bool>}>  $fields  request field => [column, setters]
     * @return array<string, string|null>
     */
    protected function stringAttributes(Request $request, array $fields, bool $onlyPresent = false): array
    {
        $attributes = [];

        foreach ($fields as $field => $definition) {
            [$column, $setters] = $definition + [1 => []];

            if ($onlyPresent && ! $request->exists($field)) {
                continue;
            }

            $attributes[$column] = TypeCaster::string($request->input($field), $field, $setters);
        }

        return $attributes;
    }
}
