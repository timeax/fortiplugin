<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Contracts;

use Timeax\FortiPlugin\Enums\PermissionType;

interface PermissionDecisionDetailInterface
{
    public function type(): PermissionType;

    /** existing natural_key (the one we are modifying) */
    public function previous(): string;

    public static function fromArray(array $a): self;

    public function toArray(): array;

    /**
     * Build a typed Upsert DTO using the current concrete row + tri-state action edits.
     * IMPORTANT: pass $this->previous() into the Upsert DTO as `natural_key: ...`
     */
    public function toUpsertDto(array $currentConcrete): UpsertDtoInterface;
}