<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Ingestion\Dto;

/** Per-rule ingestion outcome. */
final readonly class RuleIngestResult
{
    public function __construct(
        public string  $type,          // db|file|notification|module|network|codec
        public string  $natural_key,    // deterministic key/hash
        public int     $concrete_id,       // PK of the concrete row
        public string  $concrete_Type,
        public bool    $created,         // true if new concrete row created
        public bool    $assigned,        // true if plugin assignment ensured
        public ?string $warning = null
    )
    {
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'naturalKey' => $this->natural_key,
            'concreteId' => $this->concrete_id,
            'concreteType' => $this->concrete_Type,
            'created' => $this->created,
            'assigned' => $this->assigned,
            'warning' => $this->warning,
        ];
    }
}