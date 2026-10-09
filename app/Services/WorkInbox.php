<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class WorkInbox
{
    public const PAGE_SIZE = 10;

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @param  array<string, string>  $labels
     * @return array{
     *     items: Collection<int, array<string, mixed>>,
     *     total: int,
     *     filtered_total: int,
     *     active_type: string,
     *     types: Collection<int, array<string, mixed>>,
     *     groups: Collection<int, array<string, mixed>>,
     *     paginator: LengthAwarePaginator
     * }
     */
    public static function present(Collection $items, Request $request, array $labels, string $groupBy = 'class'): array
    {
        $items = $items->values();
        $type = (string) $request->query('inbox_type', '');
        if ($type !== '' && ! array_key_exists($type, $labels)) {
            $type = '';
        }

        $filtered = $type === '' ? $items : $items->where('type', $type)->values();
        $page = max(1, $request->integer('inbox_page'));
        $pageItems = $filtered->forPage($page, self::PAGE_SIZE)->values();

        return [
            'items' => $pageItems,
            'total' => $items->count(),
            'filtered_total' => $filtered->count(),
            'active_type' => $type,
            'types' => self::typeCounts($items, $labels),
            'groups' => self::grouped($pageItems, $groupBy, $labels),
            'paginator' => new LengthAwarePaginator(
                $pageItems,
                $filtered->count(),
                self::PAGE_SIZE,
                $page,
                [
                    'path' => $request->url(),
                    'pageName' => 'inbox_page',
                    'query' => $request->except('inbox_page'),
                ]
            ),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @param  array<string, string>  $labels
     * @return Collection<int, array{type: string, label: string, count: int, overdue: int, urgency: int}>
     */
    public static function typeCounts(Collection $items, array $labels): Collection
    {
        return $items->groupBy('type')->map(function (Collection $rows, string $type) use ($labels) {
            return [
                'type' => $type,
                'label' => $labels[$type] ?? $type,
                'count' => $rows->count(),
                'overdue' => $rows->where('badge', 'Overdue')->count(),
                'urgency' => (int) $rows->min('urgency'),
            ];
        })->sortBy('urgency')->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @param  array<string, string>  $labels
     * @return Collection<int, array<string, mixed>>
     */
    public static function grouped(Collection $items, string $groupBy, array $labels): Collection
    {
        return $items->groupBy('type')->map(function (Collection $rows, string $type) use ($groupBy, $labels) {
            $buckets = $groupBy === 'none'
                ? collect([[
                    'label' => null,
                    'items' => $rows->values(),
                ]])
                : $rows->groupBy(function (array $item) use ($groupBy) {
                    if ($groupBy === 'class') {
                        return trim((string) ($item['class'] ?? '')) !== '' ? $item['class'] : 'No class';
                    }

                    return trim((string) ($item['teacher'] ?? '')) !== '' ? $item['teacher'] : 'No teacher';
                })->map(fn (Collection $sub, string $label) => [
                    'label' => $label,
                    'items' => $sub->values(),
                ])->sortBy('label')->values();

            return [
                'type' => $type,
                'label' => $labels[$type] ?? $type,
                'count' => $rows->count(),
                'overdue' => $rows->where('badge', 'Overdue')->count(),
                'urgency' => (int) $rows->min('urgency'),
                'groups' => $buckets,
            ];
        })->sortBy('urgency')->values();
    }
}
