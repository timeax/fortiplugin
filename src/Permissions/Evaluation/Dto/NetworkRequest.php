<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Evaluation\Dto;

use Timeax\FortiPlugin\Permissions\Contracts\PermissionRequestInterface;

final class NetworkRequest implements PermissionRequestInterface
{
    /** @param array<string,string>|null $headers */
    public function __construct(
        public readonly string $method,    // GET|POST|...
        public readonly string $url,
        public readonly ?array $headers = null
    ) {}

    public static function fromArray(array $a): self
    {
        return new self(
            strtoupper((string)$a['method']),
            (string)$a['url'],
            isset($a['headers']) && is_array($a['headers']) ? $a['headers'] : null
        );
    }

    public function toArray(): array
    {
        return ['method' => $this->method, 'url' => $this->url, 'headers' => $this->headers];
    }
}