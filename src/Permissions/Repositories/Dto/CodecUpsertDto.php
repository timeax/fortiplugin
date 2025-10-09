<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Repositories\Dto;

use JsonException;
use Timeax\FortiPlugin\Enums\PermissionType;
use Timeax\FortiPlugin\Models\CodecPermission;

final class CodecUpsertDto extends AbstractUpsertDto
{
    public function __construct(
        public readonly string $module,  // "codec"
        /** @var array{methods?:string[]|string,groups?:string[],options?:array}|null */
        public readonly ?array $allowed,
        public readonly bool   $access
    )
    {
    }

    /** @noinspection NestedTernaryOperatorInspection */
    public static function fromNormalized(array $rule): self
    {
        // The validator already resolved/resolved_methods, options guard, etc.
        $allowed = null;
        if (isset($rule['methods']) || isset($rule['groups']) || isset($rule['options'])) {
            $allowed = [
                'methods' => isset($rule['methods'])
                    ? (is_array($rule['methods']) ? $rule['methods'] : (string)$rule['methods'])
                    : null,
                'groups' => isset($rule['groups']) ? (array)$rule['groups'] : null,
                'options' => isset($rule['options']) ? (array)$rule['options'] : null,
            ];
        }

        return new self(
            module: 'codec',
            allowed: $allowed,
            access: true
        );
    }

    public function type(): PermissionType
    {
        return PermissionType::codec;
    }

    public function concreteModelClass(): string
    {
        return CodecPermission::class;
    }

    public function identityFields(): array
    {
        return ['module', 'allowed', 'access'];
    }

    public function mutableFields(): array
    {
        return [];
    }

    /**
     * @throws JsonException
     */
    public function attributes(): array
    {
        return [
            'module' => $this->module,
            'allowed' => $this->normalize($this->allowed),
            'access' => $this->access,
        ];
    }

    /**
     * @throws JsonException
     */
    public function naturalKey(): string
    {
        $identity = [
            'module' => $this->module,
            'allowed' => $this->normalize($this->allowed),
            'access' => $this->access,
        ];
        return $this->keyFromIdentity($identity);
    }
}