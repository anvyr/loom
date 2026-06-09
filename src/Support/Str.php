<?php

declare(strict_types=1);

namespace Anvyr\Loom\Support;

final class Str
{
    private const IRREGULAR_PLURALS = [
        'child' => 'children',
        'goose' => 'geese',
        'man' => 'men',
        'woman' => 'women',
        'tooth' => 'teeth',
        'foot' => 'feet',
        'mouse' => 'mice',
        'person' => 'people',
    ];

    public static function snake(string $value, string $delimiter = '_'): string
    {
        if ($value === '') {
            return '';
        }

        // Insert delimiter before uppercase letters, then lowercase everything
        $result = preg_replace('/[A-Z]/', $delimiter . '$0', lcfirst($value));
        $result = strtolower($result ?? $value);
        $result = str_replace('-', $delimiter, $result);

        // Collapse multiple delimiters
        $result = preg_replace('/' . preg_quote($delimiter, '/') . '+/', $delimiter, $result);

        return $result ?? '';
    }

    public static function studly(string $value): string
    {
        $words = explode('_', str_replace('-', '_', $value));

        return implode('', array_map('ucfirst', $words));
    }

    public static function camel(string $value): string
    {
        return lcfirst(self::studly($value));
    }

    public static function plural(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $lower = strtolower($value);

        if (isset(self::IRREGULAR_PLURALS[$lower])) {
            return self::IRREGULAR_PLURALS[$lower];
        }

        // Words ending in s, x, z, ch, sh → add 'es'
        if (preg_match('/(?:s|x|z|ch|sh)$/', $lower)) {
            return $value . 'es';
        }

        // Words ending in consonant + y → replace y with ies
        if (preg_match('/[^aeiou]y$/', $lower)) {
            return substr($value, 0, -1) . 'ies';
        }

        return $value . 's';
    }
}
