<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Ingestion\Dto;

/** Per-rule ingestion outcome. */
final readonly class RuleIngestResult
{
    public function __construct(
        public string  $type,          // db|file|notification|module|network|codec
        public string  $naturalKey,    // deterministic key/hash
        public int     $concreteId,       // PK of the concrete row
        public bool    $created,         // true if new concrete row created
        public bool    $assigned,        // true if plugin assignment ensured
        public ?string $warning = null
    ) {}

    public function toArray(): array
    {
        return [
            'type'       => $this->type,
            'naturalKey' => $this->naturalKey,
            'concreteId' => $this->concreteId,
            'created'    => $this->created,
            'assigned'   => $this->assigned,
            'warning'    => $this->warning,
        ];
    }
}