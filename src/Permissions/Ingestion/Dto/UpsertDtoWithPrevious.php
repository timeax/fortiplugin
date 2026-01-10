<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Ingestion\Dto;

use Timeax\FortiPlugin\Enums\PermissionType;
use Timeax\FortiPlugin\Permissions\Contracts\UpsertDtoInterface;

final readonly class UpsertDtoWithPrevious implements UpsertDtoInterface
{
    public function __construct(
        private UpsertDtoInterface $inner,
        private string             $previousKey
    )
    {
    }

    public function previous(): ?string
    {
        return $this->previousKey;
    }

    public function type(): PermissionType
    {
        return $this->inner->type();
    }

    public function concreteModelClass(): string
    {
        return $this->inner->concreteModelClass();
    }

    public function identityFields(): array
    {
        return $this->inner->identityFields();
    }

    public function mutableFields(): array
    {
        return $this->inner->mutableFields();
    }

    public function attributes(): array
    {
        return $this->inner->attributes();
    }

    public function naturalKey(): string
    {
        return $this->inner->naturalKey();
    }
}