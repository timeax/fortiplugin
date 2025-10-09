<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Repositories\Dto;

use JsonException;
use Timeax\FortiPlugin\Enums\PermissionType;
use Timeax\FortiPlugin\Models\FilePermission;

final class FileUpsertDto extends AbstractUpsertDto
{
    private const ACTIONS = ['read','write','append','delete','mkdir','rmdir','list'];

    public function __construct(
        public readonly string $baseDir,
        /** @var string[] */
        public readonly array  $paths,
        /** @var array<string,bool> */
        public readonly array  $permissions
    ) {}

    public static function fromNormalized(array $rule): self
    {
        $t = (array)($rule['target'] ?? []);
        $per = array_fill_keys(self::ACTIONS, false);
        foreach ((array)($rule['actions'] ?? []) as $a) {
            if (isset($per[$a])) $per[$a] = true;
        }

        return new self(
            baseDir: (string)$t['base_dir'],
            paths: (array)$t['paths'],
            permissions: $per
        );
    }

    public function type(): PermissionType { return PermissionType::file; }

    public function concreteModelClass(): string { return FilePermission::class; }

    public function identityFields(): array
    {
        return ['base_dir','paths','permissions'];
    }

    public function mutableFields(): array
    {
        return [];
    }

    public function attributes(): array
    {
        return [
            'base_dir'    => $this->baseDir,
            'paths'       => $this->canonList($this->paths),          // normalized list
            'permissions' => $this->canonBoolMap($this->permissions, self::ACTIONS),
        ];
    }

    /**
     * @throws JsonException
     */
    public function naturalKey(): string
    {
        $identity = [
            'base_dir'   => $this->baseDir,
            'paths'      => $this->canonList($this->paths),
            'permissions'=> $this->canonBoolMap($this->permissions, self::ACTIONS),
        ];
        return $this->keyFromIdentity($identity);
    }
}