<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Evaluation\Dto\Decision;

use Timeax\FortiPlugin\Enums\PermissionType;
use Timeax\FortiPlugin\Permissions\Contracts\PermissionDecisionDetailInterface;
use Timeax\FortiPlugin\Permissions\Contracts\UpsertDtoInterface;
use Timeax\FortiPlugin\Permissions\Ingestion\Dto\NotificationUpsertDto;

final readonly class NotificationDecisionDetail implements PermissionDecisionDetailInterface
{
    private const ACTIONS = ['send','receive'];

    public function __construct(
        public string $previous,

        public ?bool $send = null,
        public ?bool $receive = null,
    ) {}

    public function type(): PermissionType { return PermissionType::notification; }

    public function previous(): string { return $this->previous; }

    public static function fromArray(array $a): self
    {
        $tri = static fn(string $k) => array_key_exists($k, $a) ? ($a[$k] === null ? null : (bool)$a[$k]) : null;

        return new self(
            previous: (string)($a['previous'] ?? ''),
            send: $tri('send'),
            receive: $tri('receive'),
        );
    }

    public function toArray(): array
    {
        return [
            'type' => 'notification',
            'previous' => $this->previous,
            'send' => $this->send,
            'receive' => $this->receive,
        ];
    }

    public function toUpsertDto(array $currentConcrete): UpsertDtoInterface
    {
        $cur = is_array($currentConcrete['permissions'] ?? null) ? $currentConcrete['permissions'] : [];
        $next = $this->mergeBoolMap($cur, [
            'send' => $this->send,
            'receive' => $this->receive,
        ]);

        return new NotificationUpsertDto(
            channel: (string)($currentConcrete['channel'] ?? ''),
            templatesAllowed: $currentConcrete['templates_allowed'] ?? null,
            recipientsAllowed: $currentConcrete['recipients_allowed'] ?? null,
            permissions: $next,
            natural_key: $this->previous
        );
    }

    private function mergeBoolMap(array $current, array $updates): array
    {
        $out = array_fill_keys(self::ACTIONS, false);

        foreach (self::ACTIONS as $k) {
            if (array_key_exists($k, $current)) $out[$k] = (bool)$current[$k];
        }

        foreach ($updates as $k => $v) {
            if ($v !== null && array_key_exists($k, $out)) $out[$k] = (bool)$v;
        }

        return $out;
    }
}