<?php

namespace Timeax\FortiPlugin\Core;

 use Timeax\FortiPlugin\Contracts\ConfigInterface;
use Timeax\FortiPlugin\Models\PluginAuditLog;
use Timeax\FortiPlugin\Support\PluginContext;
use Timeax\FortiPlugin\Exceptions\PermissionDeniedException;
use Timeax\FortiPlugin\Exceptions\PluginContextException;
use Illuminate\Http\Request;

/**
 * Trait ChecksModulePermission
 *
 * Provides unified permission checking for plugin modules.
 * Requires $type and $target to be defined in using class.
 */
trait ChecksModulePermission
{
    /**
     * Cached config class FQCN for this module instance.
     * @var class-string|null
     */
    protected ?string $cachedConfigClass = null;

    /**
     * Checks if the plugin has permission for the current operation.
     *
     * @param string|string[]|null $permissions
     * @param string|null $type Override the module type (optional)
     * @param string|null $target Override the target (optional)
     * @param Request|null $request The original request (for exception context, optional)
     * @return void
     * @throws PermissionDeniedException|PluginContextException
     * @noinspection LaravelEloquentGuardedAttributeAssignmentInspection
     */
    protected function checkModulePermission(
        string|array|null $permissions = null,
        ?string           $type = null,
        ?string           $target = null,
        ?Request          $request = null
    ): void
    {
        $type = $type ?? ($this->type ?? null);
        $target = $target ?? ($this->target ?? null);

        if (!$type || !$target) {
            throw new PluginContextException("Module permission properties \$type and \$target must be set in the module class.");
        }

        // --- CACHE THE CONFIG CLASS PER INSTANCE ---
        $configClass = $this->getPluginConfigClass();

        $info = method_exists($configClass, 'getInfo') ? $configClass::getInfo() : [];
        $pluginName = $info['name'] ?? (method_exists($configClass, 'getName') ? $configClass::getName() : 'unknown_plugin');
        $pluginId = method_exists($configClass, 'getPluginId') ? $configClass::getPluginId() : null;
        $userId = auth()->id();

        // --- CHECK PERMISSION ---
        $allowed = $configClass::getPermission($type, $target, $permissions);

        // --- AUDIT LOGGING ---
        $context = [
            'permissions' => $permissions,
            'class' => static::class,
            'method' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'] ?? null,
            'request' => $request ? [
                'method' => $request->method(),
                'uri' => $request->getRequestUri(),
                'user_agent' => $request->userAgent(),
                'ip' => $request->ip(),
                'params' => $request->all(),
            ] : null,
        ];

        PluginAuditLog::create([
            'plugin_id' => $pluginId,
            'user_id' => $userId,
            'type' => $type,
            'action' => is_array($permissions) ? implode(',', $permissions) : ($permissions ?? 'access'),
            'resource' => $target,
            'context' => array_merge($context, [
                'granted' => $allowed,
                'plugin' => $pluginName,
            ]),
        ]);

        if (!$allowed) {
            throw new PermissionDeniedException(
                $type,
                $target,
                $permissions,
                $request
            );
        }
    }

    /**
     * Immediately deny permission for the given parameters.
     *
     * @param string $message
     * @param string|null $target
     * @param string|array|null $permissions
     * @param string|null $type
     * @return void
     * @throws PermissionDeniedException
     */
    protected function denyPermission(
        string            $message,
        string|null       $target,
        string|array|null $permissions,
        ?string           $type = null
    ): void
    {
        $type = $type ?? ($this->type ?? 'module');
        throw new PermissionDeniedException(
            $type,
            $target ?? $this->target,
            $permissions,
            request(),
            $message
        );
    }

    /**
     * @return class-string<ConfigInterface>
     */
    public function getPluginConfigClass(): string
    {
        if ($this->cachedConfigClass === null) {
            $configClass = PluginContext::getCurrentConfigClass();
            if (!$configClass || !method_exists($configClass, 'getPermission')) {
                throw new PluginContextException("Unable to resolve plugin config for permission checks.");
            }
            $this->cachedConfigClass = $configClass;
        }

        return $this->cachedConfigClass;
    }
}