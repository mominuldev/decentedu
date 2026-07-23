<?php

namespace App\Support\Query;

use Illuminate\Http\Request;

/**
 * `?include=a,b` -> only the relations in $whitelist, so list endpoints don't
 * eager-load relations most callers don't render (doc 08: "?include= -> whitelisted with()").
 */
class Includes
{
    public static function resolve(Request $request, array $whitelist): array
    {
        $requested = array_filter(array_map('trim', explode(',', (string) $request->query('include', ''))));

        return array_values(array_intersect($requested, $whitelist));
    }
}
