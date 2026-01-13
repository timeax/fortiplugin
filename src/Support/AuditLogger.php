<?php

namespace Timeax\FortiPlugin\Support;

//use Timeax\FortiPlugin\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Timeax\FortiPlugin\Models\AuditLog;

//use Timeax\SecurePlugin\Models\AuditLog;

class AuditLogger
{
    /**
     * Log an action to the audit log.
     *
     * @param string $action
     * @param array|null $context
     * @param int|null $actorId (defaults to Auth::id() if available)
     * @return AuditLog
     */
    public static function log(string $action, ?array $context = null, ?int $actorId = null): AuditLog
    {
        return AuditLog::create([
            'actor' => $actorId ?? (Auth::check() ? Auth::id() : null),
            'action' => $action,
            'context' => $context,
        ]);
    }

    /**
     * Quickly log an action for the current authenticated user.
     *
     * @param string $action
     * @param array|null $context
     * @return AuditLog
     */
    public static function forCurrentUser(string $action, ?array $context = null): AuditLog
    {
        return self::log($action, $context, Auth::check() ? Auth::id() : null);
    }
}