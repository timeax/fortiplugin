<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Evaluation\Dto\Decision;

use Timeax\FortiPlugin\Enums\PermissionType;
use Timeax\FortiPlugin\Permissions\Contracts\PermissionDecisionDetailInterface;
use Timeax\FortiPlugin\Permissions\Contracts\UpsertDtoInterface;
use Timeax\FortiPlugin\Permissions\Ingestion\Dto\NetworkUpsertDto;

final readonly class NetworkDecisionDetail implements PermissionDecisionDetailInterface
{
    public function __construct(
        public string $previous,
        public ?bool $request = null,
    ) {}

    public function type(): PermissionType { return PermissionType::network; }

    public function previous(): string { return $this->previous; }

    public static function fromArray(array $a): self
    {
        return new self(
            previous: (string)($a['previous'] ?? ''),
            request: array_key_exists('request', $a) ? ($a['request'] === null ? null : (bool)$a['request']) : null,
        );
    }

    public function toArray(): array
    {
        return [
            'type' => 'network',
            'previous' => $this->previous,
            'request' => $this->request,
        ];
    }

    public function toUpsertDto(array $currentConcrete): UpsertDtoInterface
    {
        $curAccess = (bool)($currentConcrete['access'] ?? false);
        $nextAccess = $this->request === null ? $curAccess : (bool)$this->request;

        return new NetworkUpsertDto(
            hosts: (array)($currentConcrete['hosts'] ?? []),
            methods: (array)($currentConcrete['methods'] ?? []),
            schemes: $currentConcrete['schemes'] ?? null,
            ports: $currentConcrete['ports'] ?? null,
            paths: $currentConcrete['paths'] ?? null,
            headersAllowed: $currentConcrete['headers_allowed'] ?? null,
            ipsAllowed: $currentConcrete['ips_allowed'] ?? null,
            authViaHostSecret: (bool)($currentConcrete['auth_via_host_secret'] ?? true),
            access: $nextAccess,
            label: $currentConcrete['label'] ?? null,
            natural_key: $this->previous
        );
    }
}