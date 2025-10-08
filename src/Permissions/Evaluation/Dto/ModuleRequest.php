<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Evaluation\Dto;

final readonly class ModuleRequest
{
    public function __construct(
        public string $module, // alias or FQCN
        public string $api
    ) {}

    public static function fromArray(array $a): self
    {
        return new self((string)$a['module'], (string)$a['api']);
    }

    public function toArray(): array
    {
        return ['module' => $this->module, 'api' => $this->api];
    }
}