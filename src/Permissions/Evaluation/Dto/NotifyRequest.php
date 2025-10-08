<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Evaluation\Dto;

final readonly class NotifyRequest
{
    public function __construct(
        public string  $action,     // send|receive
        public string  $channel,
        public ?string $template = null,
        public ?string $recipient = null
    ) {}

    public static function fromArray(array $a): self
    {
        return new self(
            (string)$a['action'], (string)$a['channel'],
            isset($a['template']) ? (string)$a['template'] : null,
            isset($a['recipient']) ? (string)$a['recipient'] : null
        );
    }

    public function toArray(): array
    {
        return [
            'action'    => $this->action,
            'channel'   => $this->channel,
            'template'  => $this->template,
            'recipient' => $this->recipient,
        ];
    }
}