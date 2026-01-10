<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Evaluation\Dto\Decision;

use Timeax\FortiPlugin\Enums\PermissionType;
use Timeax\FortiPlugin\Permissions\Contracts\PermissionDecisionDetailInterface;
use Timeax\FortiPlugin\Permissions\Contracts\UpsertDtoInterface;
use Timeax\FortiPlugin\Permissions\Ingestion\Dto\ModuleUpsertDto;

final readonly class ModuleDecisionDetail implements PermissionDecisionDetailInterface
{
    public function __construct(
        public string $previous,
        public ?bool $call = null,
    ) {}

    public function type(): PermissionType { return PermissionType::module; }

    public function previous(): string { return $this->previous; }

    public static function fromArray(array $a): self
    {
        return new self(
            previous: (string)($a['previous'] ?? ''),
            call: array_key_exists('call', $a) ? ($a['call'] === null ? null : (bool)$a['call']) : null,
        );
    }

    public function toArray(): array
    {
        return [
            'type' => 'module',
            'previous' => $this->previous,
            'call' => $this->call,
        ];
    }

    public function toUpsertDto(array $currentConcrete): UpsertDtoInterface
    {
        $curAccess = (bool)($currentConcrete['access'] ?? false);
        $nextAccess = $this->call === null ? $curAccess : (bool)$this->call;

        return new ModuleUpsertDto(
            module: (string)($currentConcrete['module'] ?? ''),
            apis: (array)($currentConcrete['apis'] ?? []),
            access: $nextAccess,
            natural_key: $this->previous
        );
    }
}