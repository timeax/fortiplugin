<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Evaluation\Dto;

final readonly class CodecRequest
{
    public function __construct(
        public string $method,    // e.g. 'json_encode','unserialize',...
        public ?array $options = null // e.g. ['class'=>'App\DTO\Safe'] for unserialize
    ) {}

    public static function fromArray(array $a): self
    {
        return new self((string)$a['method'], isset($a['options']) && is_array($a['options']) ? $a['options'] : null);
    }

    public function toArray(): array
    {
        return ['method' => $this->method, 'options' => $this->options];
    }
}