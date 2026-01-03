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
        public ?array    $context = null,
        public ?array    $meta = null
    )
    {
    }

    public static function allow(?MorphRef $matched = null, ?array $context = null, ?array $meta = null): self
    {
        return new self(true, null, $matched, $context, $meta);
    }

    public static function deny(string $reason, ?MorphRef $matched = null, ?array $context = null, ?array $meta = null): self
    {
        return new self(false, $reason, $matched, $context, $meta);
    }

    public function toArray(): array
    {
        return [
            'allowed' => $this->allowed,
            'reason' => $this->reason,
            'matched' => $this->matched?->toArray(),
            'context' => $this->context,
            'meta' => $this->meta,
        ];
    }

    public static function fromArray(array $a): self
    {
        $allowed = (bool)($a['allowed'] ?? false);

        $reason = null;
        if (array_key_exists('reason', $a) && $a['reason'] !== null) {
            $reason = (string)$a['reason'];
        }

        $matched = null;
        if (isset($a['matched'])) {
            if ($a['matched'] instanceof MorphRef) {
                $matched = $a['matched'];
            } elseif (is_array($a['matched'])) {
                $type = (string)($a['matched']['type'] ?? '');
                $id = (int)($a['matched']['id'] ?? 0);
                if ($type !== '' && $id > 0) {
                    $matched = new MorphRef($type, $id);
                }
            }
        }

        $context = isset($a['context']) && is_array($a['context']) ? $a['context'] : null;
        $meta = isset($a['meta']) && is_array($a['meta']) ? $a['meta'] : null;

        return new self($allowed, $reason, $matched, $context, $meta);
    }
}