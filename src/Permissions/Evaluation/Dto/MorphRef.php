<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Evaluation\Dto;

/** Lightweight morph reference to a concrete permission row. */
final readonly class MorphRef
{
    public function __construct(
        public string $type, // e.g. 'db','file',...
        public int    $id
    ) {}

    public static function fromArray(array $a): self
    {
        return new self((string)($a['type'] ?? ''), (int)($a['id'] ?? 0));
    }

    public function toArray(): array
    {
        return ['type' => $this->type, 'id' => $this->id];
    }
}