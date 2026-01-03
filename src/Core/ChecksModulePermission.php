<?php

namespace Timeax\FortiPlugin\Core;

 use Timeax\FortiPlugin\Contracts\ConfigInterface;
use Timeax\FortiPlugin\Models\PluginAuditLog;
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
     * @param string            $type           Permission type (db, file, network, etc.)
     * @param string            $actionOrIntent Action (read, write, POST, invoke, etc.)
     * @param string|array| $meta           Target metadata (model name, path, host, etc.)
     * @param array             $context        Optional execution context
     * @return void
     * @throws PermissionDeniedException|PluginContextException
     */
    protected function checkModulePermission(
        string            $type,
        string            $actionOrIntent,
        string|array $meta ,
        array             $context = []
    ): void {
        $configClass = $this->getPluginConfigClass();

        // Single source of truth: one evaluation, one result
        $result = $configClass::checkPermission($type, $actionOrIntent, $meta, $context);
        $allowed = $result->allowed;


        // --- AUDIT LOGGING ---
        $pluginId = $configClass::getpluginId();

        PluginAuditLog::create([
            'plugin_id' => $pluginId,

            //TODO: INCLUDE ACTOR_ID and stuff
            'type'      => $type,
            'action'    => $actionOrIntent,
            'resource'  => is_array($meta) ? json_encode($meta) : (string)$meta,
            'context'   => array_merge($context, [
                'granted' => $allowed,
                'class'   => static::class,

                // extra useful signal (cheap + high value)
                'reason'  => $result->reason,
                'matched' => $result->matched?->toArray(),
            ]),
        ]);

        if (!$allowed) {
            throw new PermissionDeniedException(
                $type,
                $actionOrIntent,
                $meta,
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
    ): void {
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