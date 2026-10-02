<?php

namespace App\Support;

/**
 * Builds case-insensitive "contains" patterns for SQL LIKE searches.
 */
final class Like
{
    public static function contains(string $term): string
    {
        return '%'.addcslashes($term, '%_\\').'%';
    }
}
