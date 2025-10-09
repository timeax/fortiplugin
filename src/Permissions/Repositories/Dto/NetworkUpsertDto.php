<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Repositories\Dto;

use JsonException;
use Timeax\FortiPlugin\Enums\PermissionType;
use Timeax\FortiPlugin\Models\NetworkPermission;

final class NetworkUpsertDto extends AbstractUpsertDto
{
    public function __construct(
        /** @var string[] */ public readonly array $hosts,
        /** @var string[] */ public readonly array $methods,
        /** @var string[]|null */ public readonly ?array $schemes,
        /** @var int[]|null */ public readonly ?array $ports,
        /** @var string[]|null */ public readonly ?array $paths,
        /** @var string[]|null */ public readonly ?array $headersAllowed,
        /** @var string[]|null */ public readonly ?array $ipsAllowed,
                             public readonly bool $authViaHostSecret,
                             public readonly bool $access,
                             public readonly ?string $label = null
    ) {}

    public static function fromNormalized(array $rule): self
    {
        $t = (array)($rule['target'] ?? []);
        return new self(
            hosts: (array)$t['hosts'],
            methods: (array)$t['methods'],
            schemes: isset($t['schemes']) ? (array)$t['schemes'] : null,
            ports: isset($t['ports']) ? array_values(array_map('intval', $t['ports'])) : null,
            paths: isset($t['paths']) ? (array)$t['paths'] : null,
            headersAllowed: isset($t['headers_allowed']) ? (array)$t['headers_allowed'] : null,
            ipsAllowed: isset($t['ips_allowed']) ? (array)$t['ips_allowed'] : null,
            authViaHostSecret: (bool)($t['auth_via_host_secret'] ?? true),
            access: true,
            label: $rule['label'] ?? null
        );
    }

    public function type(): PermissionType { return PermissionType::network; }

    public function concreteModelClass(): string { return NetworkPermission::class; }

    public function identityFields(): array
    {
        // NOTE: 'label' intentionally not identity; mutable.
        return ['hosts','methods','schemes','ports','paths','headers_allowed','ips_allowed','auth_via_host_secret','access'];
    }

    public function mutableFields(): array
    {
        return ['label'];
    }

    public function attributes(): array
    {
        return [
            'hosts'              => $this->canonHostList($this->hosts),
            'methods'            => $this->canonList($this->methods, 'upper'),
            'schemes'            => $this->canonListOrNull($this->schemes, 'lower'),
            'ports'              => $this->canonPorts($this->ports),
            'paths'              => $this->canonListOrNull($this->paths),
            'headers_allowed'    => $this->canonListOrNull($this->headersAllowed),
            'ips_allowed'        => $this->canonListOrNull($this->ipsAllowed),
            'auth_via_host_secret'=> $this->authViaHostSecret,
            'access'             => $this->access,
            'label'              => $this->label,
        ];
    }

    /**
     * @throws JsonException
     */
    public function naturalKey(): string
    {
        $identity = [
            'hosts'               => $this->canonHostList($this->hosts),
            'methods'             => $this->canonList($this->methods, 'upper'),
            'schemes'             => $this->canonListOrNull($this->schemes, 'lower'),
            'ports'               => $this->canonPorts($this->ports),
            'paths'               => $this->canonListOrNull($this->paths),
            'headers_allowed'     => $this->canonListOrNull($this->headersAllowed),
            'ips_allowed'         => $this->canonListOrNull($this->ipsAllowed),
            'auth_via_host_secret'=> $this->authViaHostSecret,
            'access'              => $this->access,
        ];
        return $this->keyFromIdentity($identity);
    }

    private function canonHostList(array $hosts): array
    {
        $hosts = array_map('strtolower', array_map('strval', $hosts));
        return $this->canonList($hosts);
    }

    private function canonPorts(?array $ports): ?array
    {
        if ($ports === null) return null;
        $ports = array_values(array_unique(array_map('intval', $ports)));
        sort($ports, SORT_NUMERIC);
        return $ports;
    }
}