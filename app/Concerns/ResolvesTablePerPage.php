<?php

namespace App\Concerns;

use Illuminate\Http\Request;

trait ResolvesTablePerPage
{
    /**
     * Resolve a safe per-page value from the request against an allow-list.
     * Falls back to the default when the requested value is not permitted,
     * protecting the query from an arbitrary `paginate()` size.
     *
     * @param  array<int, int>  $allowed
     */
    protected function resolveTablePerPage(
        Request $request,
        array $allowed = [10, 25, 50, 100],
        int $default = 10,
    ): int {
        $perPage = $request->integer('per_page');

        return in_array($perPage, $allowed, true) ? $perPage : $default;
    }
}
