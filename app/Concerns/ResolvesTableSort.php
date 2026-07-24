<?php

namespace App\Concerns;

use Illuminate\Http\Request;

trait ResolvesTableSort
{
    /**
     * Resolve a safe sort column and direction from the request against an
     * allow-list. Falls back to the default when the requested column is not
     * permitted, protecting the query from arbitrary `orderBy` input.
     *
     * @param  array<int, string>  $sortable  columns the client may sort by
     * @param  'asc'|'desc'  $defaultDirection
     * @return array{sort: string, direction: 'asc'|'desc'}
     */
    protected function resolveTableSort(
        Request $request,
        array $sortable,
        string $default,
        string $defaultDirection = 'asc',
    ): array {
        $sort = $request->string('sort')->trim()->value();
        $direction = $request->string('direction')->lower()->value() === 'desc' ? 'desc' : 'asc';

        if ($sort === '' || ! in_array($sort, $sortable, true)) {
            return ['sort' => $default, 'direction' => $defaultDirection];
        }

        return ['sort' => $sort, 'direction' => $direction];
    }
}
