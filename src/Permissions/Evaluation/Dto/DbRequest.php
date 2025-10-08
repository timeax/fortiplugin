<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Evaluation\Dto;

final readonly class DbRequest
{
    /** @param string[]|null $columns */
    public function __construct(
        public string  $action,                 // select|insert|update|delete|truncate|grouped_queries
        public ?string $modelAliasOrFqcn = null,
        public ?string $table = null,
        public ?array  $columns = null
    ) {}

    public static function fromArray(array $a): self
    {
        return new self(
            action: (string)$a['action'],
            modelAliasOrFqcn: isset($a['model']) ? (string)$a['model'] : (null),
            table: isset($a['table']) ? (string)$a['table'] : (null),
            columns: isset($a['columns']) && is_array($a['columns']) ? array_values(array_unique(array_map('strval', $a['columns']))) : null
        );
    }

    public function toArray(): array
    {
        return [
            'action' => $this->action,
            'model'  => $this->modelAliasOrFqcn,
            'table'  => $this->table,
            'columns'=> $this->columns,
        ];
    }
}