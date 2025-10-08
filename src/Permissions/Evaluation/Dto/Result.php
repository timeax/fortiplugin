<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Evaluation\Dto;

/**
 * Standardized decision result for all `can*` calls.
 */
final readonly class Result
{
    public function __construct(
        public bool      $allowed,
        public ?string   $reason = null,
        public ?MorphRef $matched = null,
        public ?array    $context = null
    ) {}

    public static function allow(?MorphRef $matched = null, ?array $context = null): self
    {
        return new self(true, null, $matched, $context);
    }

    public static function deny(string $reason, ?MorphRef $matched = null, ?array $context = null): self
    {
        return new self(false, $reason, $matched, $context);
    }

    public function toArray(): array
    {
        return [
            'allowed' => $this->allowed,
            'reason'  => $this->reason,
            'matched' => $this->matched?->toArray(),
            'context' => $this->context,
        ];
    }
}