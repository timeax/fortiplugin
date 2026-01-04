<?php

namespace Timeax\FortiPlugin\Events;

final readonly class PluginPermissionDenied
{
    public function __construct(
        public string $type,
        public string $action,
        public string|array|null $meta,
        public ?string $reason,
        public ?array $matched,
        public ?array $context,
        public string $message,
        public array $requestData,
        public ?string $pluginAlias = null,
    ) {
    }
}
