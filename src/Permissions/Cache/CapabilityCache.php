<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Cache;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use JsonException;
use Psr\SimpleCache\InvalidArgumentException;
use Timeax\FortiPlugin\Permissions\Contracts\CapabilityCacheInterface;

/**
 * Stores a compiled capability map per plugin and a separate ETag.
 *
 * Cache keys:
 *  - {prefix}:cap:{pluginId}
 *  - {prefix}:etag:{pluginId}
 */
final class CapabilityCache implements CapabilityCacheInterface
{
    private string $prefix;
    private int $defaultTtl;

    public function __construct(
        private readonly CacheRepository $cache
    ) {
        $this->prefix     = (string) config('fortiplugin.permissions.cache_prefix', 'fortiplugin:capabilities');
        $this->defaultTtl = (int) (config('fortiplugin.permissions.cache_ttl', 300)); // seconds
    }

    /**
     * @throws InvalidArgumentException
     */
    public function get(int $pluginId): ?array
    {
        $data = $this->cache->get($this->capKey($pluginId));
        return is_array($data) ? $data : null;
    }

    /**
     * @throws JsonException
     */
    public function put(int $pluginId, array $capabilities, ?int $ttlSeconds = null, ?string $etag = null): void
    {
        $ttl = $ttlSeconds ?? $this->defaultTtl;

        // Store capabilities
        if ($ttl > 0) {
            $this->cache->put($this->capKey($pluginId), $capabilities, $ttl);
        } else {
            // store forever (driver dependent)
            $this->cache->forever($this->capKey($pluginId), $capabilities);
        }

        // Compute or set ETag
        $etag = $etag ?? KeyBuilder::fromCapabilities($capabilities);
        if ($ttl > 0) {
            $this->cache->put($this->etagKey($pluginId), $etag, $ttl);
        } else {
            $this->cache->forever($this->etagKey($pluginId), $etag);
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    public function etag(int $pluginId): ?string
    {
        $val = $this->cache->get($this->etagKey($pluginId));
        return is_string($val) ? $val : null;
    }

    public function invalidate(int $pluginId): void
    {
        $this->cache->forget($this->capKey($pluginId));
        $this->cache->forget($this->etagKey($pluginId));
    }

    private function capKey(int $pluginId): string
    {
        return "{$this->prefix}:cap:{$pluginId}";
    }

    private function etagKey(int $pluginId): string
    {
        return "{$this->prefix}:etag:{$pluginId}";
    }
}