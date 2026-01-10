<?php /** @noinspection DuplicatedCode */
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Evaluation\Dto\Decision;

use Timeax\FortiPlugin\Enums\PermissionType;
use Timeax\FortiPlugin\Permissions\Contracts\PermissionDecisionDetailInterface;
use Timeax\FortiPlugin\Permissions\Contracts\UpsertDtoInterface;
use Timeax\FortiPlugin\Permissions\Ingestion\Dto\FileUpsertDto;

final readonly class FileDecisionDetail implements PermissionDecisionDetailInterface
{
    private const ACTIONS = ['read','write','append','delete','mkdir','rmdir','list'];

    public function __construct(
        public string $previous,

        public ?bool $read = null,
        public ?bool $write = null,
        public ?bool $append = null,
        public ?bool $delete = null,
        public ?bool $mkdir = null,
        public ?bool $rmdir = null,
        public ?bool $list = null,
    ) {}

    public function type(): PermissionType { return PermissionType::file; }

    public function previous(): string { return $this->previous; }

    public static function fromArray(array $a): self
    {
        $tri = static fn(string $k) => array_key_exists($k, $a) ? ($a[$k] === null ? null : (bool)$a[$k]) : null;

        return new self(
            previous: (string)($a['previous'] ?? ''),
            read: $tri('read'),
            write: $tri('write'),
            append: $tri('append'),
            delete: $tri('delete'),
            mkdir: $tri('mkdir'),
            rmdir: $tri('rmdir'),
            list: $tri('list'),
        );
    }

    public function toArray(): array
    {
        return [
            'type' => 'file',
            'previous' => $this->previous,
            'read' => $this->read,
            'write' => $this->write,
            'append' => $this->append,
            'delete' => $this->delete,
            'mkdir' => $this->mkdir,
            'rmdir' => $this->rmdir,
            'list' => $this->list,
        ];
    }

    public function toUpsertDto(array $currentConcrete): UpsertDtoInterface
    {
        $cur = is_array($currentConcrete['permissions'] ?? null) ? $currentConcrete['permissions'] : [];
        $next = $this->mergeBoolMap($cur, [
            'read' => $this->read,
            'write' => $this->write,
            'append' => $this->append,
            'delete' => $this->delete,
            'mkdir' => $this->mkdir,
            'rmdir' => $this->rmdir,
            'list' => $this->list,
        ]);

        return new FileUpsertDto(
            baseDir: (string)($currentConcrete['base_dir'] ?? ''),
            paths: (array)($currentConcrete['paths'] ?? []),
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