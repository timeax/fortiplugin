<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Contracts;

use Illuminate\Database\Eloquent\Model;
use Timeax\FortiPlugin\Enums\PermissionType;

interface UpsertDtoInterface
{
    /** Concrete permission type (db|file|notification|module|network|codec). */
    public function type(): PermissionType;

    /** Natural key built from identity-defining attributes (stable, deterministic). */
    public function naturalKey(): string;

    /**
     * Eloquent concrete model FQCN (e.g., DbPermission::class).
     * @return class-string<Model>
     */
    public function concreteModelClass(): string;

    /**
     * Full attribute bag to persist on first insert (identity + allowed mutables).
     * Only fields present here are ever written by the repository.
     */
    public function attributes(): array;

    /** List of identity-defining field names (must match stored identity on hit). */
    public function identityFields(): array;

    /** List of fields allowed to change without changing identity. */
    public function mutableFields(): array;
}