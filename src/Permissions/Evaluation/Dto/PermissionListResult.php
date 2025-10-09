<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Evaluation\Dto;

/**
 * Final list payload: items + summary.
 */
final readonly class PermissionListResult
{
    /** @param PermissionListItem[] $items */
    public function __construct(
        public array                $items,
        public PermissionListSummary $summary
    ) {}

    public function toArray(): array
    {
        return [
            'items'   => array_map(static fn($i) => $i instanceof PermissionListItem ? $i->toArray() : $i, $this->items),
            'summary' => $this->summary->toArray(),
        ];
    }
}