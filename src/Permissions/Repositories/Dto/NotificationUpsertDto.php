<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Repositories\Dto;

use JsonException;
use Timeax\FortiPlugin\Enums\PermissionType;
use Timeax\FortiPlugin\Models\NotificationPermission;

final class NotificationUpsertDto extends AbstractUpsertDto
{
    private const ACTIONS = ['send','receive'];

    public function __construct(
        public readonly string  $channel,
        /** @var string[]|null */
        public readonly ?array  $templatesAllowed,
        /** @var string[]|null */
        public readonly ?array  $recipientsAllowed,
        /** @var array<string,bool> */
        public readonly array   $permissions
    ) {}

    public static function fromNormalized(array $rule): self
    {
        $t = (array)($rule['target'] ?? []);
        $per = array_fill_keys(self::ACTIONS, false);
        foreach ((array)($rule['actions'] ?? []) as $a) {
            if (isset($per[$a])) $per[$a] = true;
        }

        return new self(
            channel: (string)$t['channels'][0], // your validator ensures non-empty; you ingest per-channel
            templatesAllowed: isset($t['templates']) ? (array)$t['templates'] : null,
            recipientsAllowed: isset($t['recipients']) ? (array)$t['recipients'] : null,
            permissions: $per
        );
    }

    public function type(): PermissionType { return PermissionType::notification; }

    public function concreteModelClass(): string { return NotificationPermission::class; }

    public function identityFields(): array
    {
        return ['channel','templates_allowed','recipients_allowed','permissions'];
    }

    public function mutableFields(): array
    {
        return [];
    }

    public function attributes(): array
    {
        return [
            'channel'            => $this->channel,
            'templates_allowed'  => $this->canonListOrNull($this->templatesAllowed),
            'recipients_allowed' => $this->canonListOrNull($this->recipientsAllowed),
            'permissions'        => $this->canonBoolMap($this->permissions, self::ACTIONS),
        ];
    }

    /**
     * @throws JsonException
     */
    public function naturalKey(): string
    {
        $identity = [
            'channel'            => $this->channel,
            'templates_allowed'  => $this->canonListOrNull($this->templatesAllowed),
            'recipients_allowed' => $this->canonListOrNull($this->recipientsAllowed),
            'permissions'        => $this->canonBoolMap($this->permissions, self::ACTIONS),
        ];
        return $this->keyFromIdentity($identity);
    }
}