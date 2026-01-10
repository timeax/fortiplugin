<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Evaluation\Dto\Decision;

use Timeax\FortiPlugin\Enums\PermissionType;
use Timeax\FortiPlugin\Permissions\Contracts\PermissionDecisionDetailInterface;
use Timeax\FortiPlugin\Permissions\Contracts\UpsertDtoInterface;
use Timeax\FortiPlugin\Permissions\Ingestion\Dto\CodecUpsertDto;

final readonly class CodecDecisionDetail implements PermissionDecisionDetailInterface
{
    public function __construct(
        public string $previous,
        public ?bool $invoke = null,
    ) {}

    public function type(): PermissionType { return PermissionType::codec; }

    public function previous(): string { return $this->previous; }

    public static function fromArray(array $a): self
    {
        return new self(
            previous: (string)($a['previous'] ?? ''),
            invoke: array_key_exists('invoke', $a) ? ($a['invoke'] === null ? null : (bool)$a['invoke']) : null,
        );
    }

    public function toArray(): array
    {
        return [
            'type' => 'codec',
            'previous' => $this->previous,
            'invoke' => $this->invoke,
        ];
    }

    public function toUpsertDto(array $currentConcrete): UpsertDtoInterface
    {
        $curAccess = (bool)($currentConcrete['access'] ?? false);
        $nextAccess = $this->invoke === null ? $curAccess : (bool)$this->invoke;

        return new CodecUpsertDto(
            module: (string)($currentConcrete['module'] ?? 'codec'),
            allowed: $currentConcrete['allowed'] ?? null,
            access: $nextAccess,
            natural_key: $this->previous
        );
    }
}