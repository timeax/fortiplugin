<?php

namespace Timeax\FortiPlugin\Core;

use Timeax\FortiPlugin\Contracts\ConfigInterface;
use Timeax\FortiPlugin\Models\PluginAuditLog;
use Timeax\FortiPlugin\Permissions\Contracts\PermissionRequestInterface;
use Timeax\FortiPlugin\Support\PluginContext;
use Timeax\FortiPlugin\Exceptions\PermissionDeniedException;
use Timeax\FortiPlugin\Exceptions\PluginContextException;


use Timeax\FortiPlugin\Permissions\Evaluation\Dto\Result;

/**
 * Trait ChecksModulePermission
 *
 * Provides unified permission checking for plugin modules.
 */
trait ChecksModulePermission
{
    /**
     * Cached config class FQCN for this module instance.
     * @var class-string<ConfigInterface>|null
     */
    protected ?string $cachedConfigClass = null;

    /**
     * Checks if the plugin has permission for the current operation.
     *
     * @param PermissionRequestInterface $request The permission request DTO
     * @param array $context Optional execution context
     * @return void
     * @throws PermissionDeniedException|PluginContextException
     */
    protected function checkModulePermission(
        PermissionRequestInterface $request,
        array                      $context = []
    ): void
    {
        $configClass = $this->getPluginConfigClass();

        // Single source of truth: one evaluation, one result
        $result = $configClass::checkPermission($request, $context);
        $allowed = $result->allowed;


        // --- AUDIT LOGGING ---
        $pluginId = $configClass::getpluginId();

        // Extract basic info from request for logging
        // Assuming request DTOs have getters or public properties for type/action/target
        // We'll need to inspect the request object to log meaningful data
        // Since PermissionRequestInterface doesn't enforce specific getters, we might need to rely on concrete types or reflection/casting if we want specific fields.
        // However, for now, let's serialize the request itself or use a generic approach.

        // A simple way is to log the request class and its serialized form or specific known fields if possible.
        // But to keep it generic and robust:
        $type = 'unknown';
        $action = 'unknown';
        $resource = 'unknown';

        // Attempt to extract common fields if they exist on the request object (duck typing or interface expansion later)
        // For now, let's just use the class name as type and serialize the object for resource/context
        $type = (new \ReflectionClass($request))->getShortName();
        $resource = json_encode($request);


        PluginAuditLog::create([
            'plugin_id' => $pluginId,
            'type' => $type,
            'action' => $action, // We might want to extract this if possible, or leave as unknown/generic
            'resource' => $resource,
            'context' => array_merge($context, [
                'granted' => $allowed,
                'class' => static::class,

                // extra useful signal (cheap + high value)
                'reason' => $result->reason,
                'matched' => $result->matched?->toArray(),
            ]),
        ]);

        if (!$allowed) {
            throw new PermissionDeniedException(
                $type,
                $action,
                $resource,
                $result,
                request()
            );
        }
    }


    /**
     * Immediately deny permission for the given parameters.
     */
    protected function denyPermission(
        string            $type,
        string            $actionOrIntent,
        string|array|null $meta = null,
        ?string           $reason = null
    ): void
    {
        $result = Result::deny($reason ?? 'explicit_denial');

        throw new PermissionDeniedException(
            $type,
            $actionOrIntent,
            $meta,
            $result,
            request()
        );
    }

    /**
     * @return class-string<ConfigInterface>
     */
    public function getPluginConfigClass(): string
    {
        if ($this->cachedConfigClass === null) {
            $configClass = PluginContext::getCurrentConfigClass();
            if (!$configClass || !is_subclass_of($configClass, ConfigInterface::class)) {
                throw new PluginContextException("Resolved config class must implement ConfigInterface.");
            }
            $this->cachedConfigClass = $configClass;
        }

        return $this->cachedConfigClass;
    }
}