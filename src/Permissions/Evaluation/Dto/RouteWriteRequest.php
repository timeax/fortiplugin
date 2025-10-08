<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Evaluation\Dto;

final readonly class RouteWriteRequest
{
    public function __construct(
        public string  $routeId,
        public ?string $guard = null
    ) {}

    public static function fromArray(array $a): self
    {
        return new self((string)$a['routeId'], isset($a['guard']) ? (string)$a['guard'] : null);
    }

    public function toArray(): array
    {
        return ['routeId' => $this->routeId, 'guard' => $this->guard];
    }
}