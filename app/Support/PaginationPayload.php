<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * The pagination payload every list page sends to the front end.
 *
 * The shared <Pagination> component needs the page numbers, the "showing x-y
 * of z" range, and the current filters, so that every page link keeps the
 * active search instead of dropping it. Building it in one place keeps the
 * pages from each inventing their own half of the shape.
 */
class PaginationPayload
{
    /**
     * Leaving $path and $query out takes them from the current request, which
     * is what a list page wants: the page it is on, keeping whatever filters
     * are in the URL. Pass them only to override that.
     *
     * @param  array<string, mixed>|null  $query  filters to carry into every page link
     * @return array<string, mixed>
     */
    public static function make(LengthAwarePaginator $paginator, ?string $path = null, ?array $query = null): array
    {
        $query ??= \Illuminate\Support\Arr::except(request()->query(), ['page']);

        return [
            'has_pages' => $paginator->hasPages(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
            'path' => $path ?: url()->current(),
            // Blank filters would otherwise turn into "?search=" on every link.
            'query' => array_filter(
                $query,
                fn ($value) => $value !== null && $value !== '' && $value !== []
            ),
            'previous_url' => $paginator->previousPageUrl(),
            'next_url' => $paginator->nextPageUrl(),
        ];
    }
}
