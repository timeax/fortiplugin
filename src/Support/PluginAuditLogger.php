<?php

namespace Timeax\FortiPlugin\Support;

use Timeax\FortiPlugin\Models\PluginAuditLog;

class PluginAuditLogger
{
    /**
     * Log a plugin permission/access event.
     *
     * @param int $pluginId The plugin's ID.
     * @param string $type The permission type ('db', 'file', 'notification', 'module').
     * @param string $action The action performed ('select', 'write', 'send', etc.).
     * @param string $resource The resource accessed (table name, file path, channel, etc.).
     * @param int|null $userId The user/developer who initiated the action (nullable).
     * @param array $context Optional extra context (query run, params, IP, etc.).
     */
    public static function log(
        int    $pluginId,
        string $type,
        string $action,
        string $resource,
        ?int   $userId = null,
        array  $context = []
    ): void
    {
        PluginAuditLog::create([
            'plugin_id' => $pluginId,
            'actor_author_id' => $userId,
            'type' => $type,
            'action' => $action,
            'resource' => $resource,
            'context' => $context,
        ]);
    }
}