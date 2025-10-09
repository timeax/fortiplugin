<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Evaluation\Dto;

/**
 * Optional filters/options for the list operation.
 * All fields optional; omit to fetch the full, unfiltered view.
 */
final readonly class PermissionListOptions
{
    public function __construct(
        public ?string $type = null,           // "db"|"file"|"notification"|"module"|"network"|"codec"
        public ?bool   $requiredOnly = null,   // true=only required, false=only optional, null=both
        public ?bool   $activeOnly = null,     // true=only active_effective, false=only inactive, null=both
        public ?string $source = null,         // "direct"|"tag"|"both" (default both)
        public ?int    $tagId = null,          // limit to morphs that come via a specific tag
        public ?string $search = null,         // future use (e.g., by model/table/host/path)
        public ?int    $page = null,           // future use (server-side pagination)
        public ?int    $perPage = null         // future use
    ) {}

    public static function fromArray(array $a): self
    {
        return new self(
            type:         isset($a['type']) ? (string)$a['type'] : null,
            requiredOnly: array_key_exists('requiredOnly', $a) ? (bool)$a['requiredOnly'] : null,
            activeOnly:   array_key_exists('activeOnly',   $a) ? (bool)$a['activeOnly']   : null,
            source:       isset($a['source']) ? (string)$a['source'] : null,
            tagId:        isset($a['tagId']) ? (int)$a['tagId'] : null,
            search:       isset($a['search']) ? (string)$a['search'] : null,
            page:         isset($a['page']) ? (int)$a['page'] : null,
            perPage:      isset($a['perPage']) ? (int)$a['perPage'] : null,
        );
    }

    public function toArray(): array
    {
        return [
            'type'         => $this->type,
            'requiredOnly' => $this->requiredOnly,
            'activeOnly'   => $this->activeOnly,
            'source'       => $this->source,
            'tagId'        => $this->tagId,
            'search'       => $this->search,
            'page'         => $this->page,
            'perPage'      => $this->perPage,
        ];
    }
}