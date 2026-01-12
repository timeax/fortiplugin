<?php /** @noinspection DuplicatedCode */
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Evaluation\Dto\Decision;

use Timeax\FortiPlugin\Enums\PermissionType;
use Timeax\FortiPlugin\Permissions\Contracts\PermissionDecisionDetailInterface;
use Timeax\FortiPlugin\Permissions\Contracts\UpsertDtoInterface;
use Timeax\FortiPlugin\Permissions\Ingestion\Dto\DbUpsertDto;

final readonly class DbDecisionDetail implements PermissionDecisionDetailInterface
{
    private const ACTIONS = ['select','insert','update','delete','truncate','grouped_queries'];

    public function __construct(
        public string $previous,

        public ?bool $select = null,
        public ?bool $insert = null,
        public ?bool $update = null,
        public ?bool $delete = null,
        public ?bool $truncate = null,
        public ?bool $grouped_queries = null,
    ) {}

    public function type(): PermissionType { return PermissionType::db; }

    public function previous(): string { return $this->previous; }

    public static function fromArray(array $a): self
    {
        return new self(
            previous: (string)($a['previous'] ?? ''),
            select: array_key_exists('select', $a) ? ($a['select'] === null ? null : (bool)$a['select']) : null,
            insert: array_key_exists('insert', $a) ? ($a['insert'] === null ? null : (bool)$a['insert']) : null,
            update: array_key_exists('update', $a) ? ($a['update'] === null ? null : (bool)$a['update']) : null,
            delete: array_key_exists('delete', $a) ? ($a['delete'] === null ? null : (bool)$a['delete']) : null,
            truncate: array_key_exists('truncate', $a) ? ($a['truncate'] === null ? null : (bool)$a['truncate']) : null,
            grouped_queries: array_key_exists('grouped_queries', $a) ? ($a['grouped_queries'] === null ? null : (bool)$a['grouped_queries']) : null,
        );
    }

    public function toArray(): array
    {
        return [
            'type' => 'db',
            'previous' => $this->previous,
            'select' => $this->select,
            'insert' => $this->insert,
            'update' => $this->update,
            'delete' => $this->delete,
            'truncate' => $this->truncate,
            'grouped_queries' => $this->grouped_queries,
        ];
    }

    public function toUpsertDto(array $currentConcrete): UpsertDtoInterface
    {
        $cur = is_array($currentConcrete['permissions'] ?? null) ? $currentConcrete['permissions'] : [];
        $next = $this->mergeBoolMap($cur, [
            'select' => $this->select,
            'insert' => $this->insert,
            'update' => $this->update,
            'delete' => $this->delete,
            'truncate' => $this->truncate,
            'grouped_queries' => $this->grouped_queries,
        ]);

        return new DbUpsertDto(
            model: $currentConcrete['model'] ?? null,
            table: $currentConcrete['table'] ?? null, // even if not stored currently, keep it consistent if present
            readableColumns: $currentConcrete['readable_columns'] ?? null,
            writableColumns: $currentConcrete['writable_columns'] ?? null,
            permissions: $next,
            natural_key: $this->previous
        );
    }

    private function mergeBoolMap(array $current, array $updates): array
    {
        $out = array_fill_keys(self::ACTIONS, false);

        foreach (self::ACTIONS as $k) {
            if (array_key_exists($k, $current)) $out[$k] = (bool)$current[$k];
        }

        foreach ($updates as $k => $v) {
            if ($v !== null && array_key_exists($k, $out)) $out[$k] = (bool)$v;
        }

        return $out;
    }
}