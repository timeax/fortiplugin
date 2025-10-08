<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Contracts;

/**
 * Per-type checker (db/file/notification/module/network/codec/route).
 * Implementations should be stateless and rely on injected repositories/cache/catalogs.
 */
interface PermissionCheckerInterface
{
    /**
     * @return string One of: db|file|notification|module|network|codec|route
     */
    public function type(): string;

    /**
     * Check a request against a plugin's capabilities (often via cache).
     *
     * @param int   $pluginId
     * @param PermissionRequestInterface $request  Type-specific request payload (see PermissionServiceInterface docblocks).
     * @param array $context  guard/env/settings etc.
     * @return array Standard result: ['allowed'=>bool,'reason'=>?string,'matched'=>?['type'=>string,'id'=>int],'context'=>?array]
     */
    public function check(int $pluginId, PermissionRequestInterface $request, array $context = []): array;
}