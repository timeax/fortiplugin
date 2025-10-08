<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Ingestion\Dto;

final readonly class IngestSummary
{
    /** @param RuleIngestResult[] $items */
    public function __construct(
        public int   $created,     // number of new concrete rows
        public int   $linked,      // number of plugin assignments ensured
        public array $items = [],// per-rule results
        public array $warnings = []
    )
    {
    }

    public function toArray(): array
    {
        return [
            'created' => $this->created,
            'linked' => $this->linked,
            'items' => array_map(static fn($i) => $i instanceof RuleIngestResult ? $i->toArray() : $i, $this->items),
            'warnings' => $this->warnings,
        ];
    }

    public static function merge(self ...$parts): self
    {
        $created = 0;
        $linked = 0;
        $items = [];
        $warnings = [];
        foreach ($parts as $p) {
            $created += $p->created;
            $linked += $p->linked;
            array_push($items, ...$p->items);
            array_push($warnings, ...$p->warnings);
        }
        return new self($created, $linked, $items, $warnings);
    }
}