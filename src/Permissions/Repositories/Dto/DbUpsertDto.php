<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Repositories\Dto;

use JsonException;
use Timeax\FortiPlugin\Enums\PermissionType;
use Timeax\FortiPlugin\Models\DbPermission;

final class DbUpsertDto extends AbstractUpsertDto
{
    private const ACTIONS = ['select','insert','update','delete','truncate','grouped_queries'];

    public function __construct(
        public readonly ?string $model,                  // alias or FQCN (nullable if table-based – you said model/table but Prisma now only holds 'model')
        public readonly ?string $table,                  // kept here if you decide to re-add table later; ignored by attributes if you didn’t
        /** @var string[]|null */
        public readonly ?array  $readableColumns,
        /** @var string[]|null */
        public readonly ?array  $writableColumns,
        /** @var array<string,bool> */
        public readonly array   $permissions
    ) {}

    public static function fromNormalized(array $rule): self
    {
        // $rule['target'] was already normalized by ManifestValidator (model alias→FQCN if known, columns validated)
        $t   = (array)($rule['target'] ?? []);
        $per = array_fill_keys(self::ACTIONS, false);
        foreach ((array)($rule['actions'] ?? []) as $a) {
            if (isset($per[$a])) $per[$a] = true;
        }

        return new self(
            model: isset($t['model']) ? (string)$t['model'] : null,
            table: $t['table'] ?? null, // preserved if you later store table
            readableColumns: $t['columns'] ?? null, // if you use columns here
            writableColumns: null, // you can pass host policy here if you choose to persist it
            permissions: $per
        );
    }

    public function type(): PermissionType { return PermissionType::db; }

    public function concreteModelClass(): string { return DbPermission::class; }

    public function identityFields(): array
    {
        // Identity = the target + the action map
        return ['model','table','readable_columns','writable_columns','permissions'];
    }

    public function mutableFields(): array
    {
        // DB concrete is immutable by default
        return [];
    }

    public function attributes(): array
    {
        return [
            'model'             => $this->model,
            // 'table'          => $this->table, // include if your Eloquent model/table has it
            'readable_columns'  => $this->canonListOrNull($this->readableColumns),
            'writable_columns'  => $this->canonListOrNull($this->writableColumns),
            'permissions'       => $this->canonBoolMap($this->permissions, self::ACTIONS),
        ];
    }

    /**
     * @throws JsonException
     */
    public function naturalKey(): string
    {
        $identity = [
            'model'            => $this->model,
            'table'            => $this->table,
            'readable_columns' => $this->canonListOrNull($this->readableColumns),
            'writable_columns' => $this->canonListOrNull($this->writableColumns),
            'permissions'      => $this->canonBoolMap($this->permissions, self::ACTIONS),
        ];
        return $this->keyFromIdentity($identity);
    }
}