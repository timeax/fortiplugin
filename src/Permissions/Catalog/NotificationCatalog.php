<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Catalog;

use JsonException;
use Timeax\FortiPlugin\Permissions\Cache\KeyBuilder;
use Timeax\FortiPlugin\Permissions\Support\HostConfigNormalizer;

/**
 * Catalog of notification channels defined by the host.
 * Config can be either:
 *  - ['email','sms','push']  OR  ['email'=>true,'sms'=>true,'push'=>true]
 */
final class NotificationCatalog
{
    /** @var string[] */
    private array $channels;

    public function __construct(?array $config = null)
    {
        $this->channels = $this->normalize($config ?? $this->readConfig());
    }

    /** @return string[] */
    public function channels(): array
    {
        return $this->channels;
    }

    public function has(string $channel): bool
    {
        return in_array($channel, $this->channels, true);
    }

    /**
     * @throws JsonException
     */
    public function revision(): string
    {
        return KeyBuilder::fromCapabilities($this->channels);
    }

    /* ------------------------ internals ------------------------ */

    /** @param array<string,mixed>|array<int,string> $channels */
    private function normalize(array $channels): array
    {
        return HostConfigNormalizer::notificationChannels($channels);
    }

    private function readConfig(): mixed
    {
        $default = [];
        return function_exists('config') ? config('fortiplugin-maps.notifications-channels', $default) : $default;
    }
}