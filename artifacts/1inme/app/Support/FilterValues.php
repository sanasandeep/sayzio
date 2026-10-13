<?php

namespace App\Support;

use Illuminate\Http\Request;

/** Backwards-compatible scalar filters plus explicit multi-value query parameters. */
final class FilterValues
{
    public static function get(Request $request, string $key, ?array $allowed = null): array
    {
        $raw = $request->query($key.'_multi', $request->query($key, []));
        $values = array_values(array_unique(array_filter(array_map(fn ($v) => is_scalar($v) ? trim((string) $v) : '', is_array($raw) ? array_slice($raw, 0, 100) : [$raw]), fn ($v) => $v !== '' && strlen($v) <= 200)));

        return $allowed === null ? $values : array_values(array_intersect($values, array_map('strval', $allowed)));
    }

    public static function ids(Request $request, string $key): array
    {
        return array_values(array_map('intval', array_filter(self::get($request, $key), fn ($v) => ctype_digit($v) && strlen($v) < 19)));
    }
}
