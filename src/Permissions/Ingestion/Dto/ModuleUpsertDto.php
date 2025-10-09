<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Ingestion\Dto;

use JsonException;
use Timeax\FortiPlugin\Enums\PermissionType;
use Timeax\FortiPlugin\Models\ModulePermission;

final class ModuleUpsertDto extends AbstractUpsertDto
{
    public function __construct(
        public readonly string $module, // alias or FQCN (already normalized by validator if host map known)
        /** @var string[] */
        public readonly array  $apis,
        public readonly bool   $access // action "call" is represented as access=true/false
    ) {}

    public static function fromNormalized(array $rule): self
    {
        $t = (array)($rule['target'] ?? []);
        return new self(
            module: (string)$t['plugin_fqcn'] ?: (string)$t['plugin'],
            apis: (array)$t['apis'],
            access: true // if rule exists, you’re granting "call" (ingestor can set to true)
        );
    }

    public function type(): PermissionType { return PermissionType::module; }

    public function concreteModelClass(): string { return ModulePermission::class; }

    public function identityFields(): array
    {
        return ['module','apis','access'];
    }

    public function mutableFields(): array
    {
        return [];
    }

    public function attributes(): array
    {
        return [
            'module' => $this->module,
            'apis'   => $this->canonList($this->apis),
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
            'apis'   => $this->canonList($this->apis),
            'access' => $this->access,
        ];
        return $this->keyFromIdentity($identity);
    }
}