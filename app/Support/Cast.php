<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Narrow a loosely-typed value to a concrete PHP type.
 *
 * Raw SQL aggregate columns are genuinely `mixed`: SUM() comes back as an int on
 * SQLite and as a numeric string on MySQL, and COUNT() over an empty set can be null
 * once COALESCE is out of the picture. A bare `(int)` cast hides that difference and
 * silently turns a non-numeric surprise into 0, so these helpers make the narrowing
 * explicit and give the fallback a name.
 *
 * This is for values arriving from outside PHP's type system — the database, Faker,
 * decoded JSON. Anywhere a real type is already available, use it instead.
 */
final class Cast
{
    public static function int(mixed $value, int $default = 0): int
    {
        return is_numeric($value) ? (int) $value : $default;
    }

    public static function string(mixed $value, string $default = ''): string
    {
        return is_scalar($value) ? (string) $value : $default;
    }
}
