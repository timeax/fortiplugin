<?php

namespace Timeax\FortiPlugin\Traits;

use InvalidArgumentException;
use JsonException;
use Timeax\FortiPlugin\Models\PluginSetting;
use Timeax\FortiPlugin\Support\HostConfigToPluginSettings;

trait PluginSettingsLoader
{
    /**
     * Example: load host config (inline or from file path),
     * convert to rows, then upsert into plugin_settings.
     *
     * @throws JsonException
     */
    public function installHostConfig(int $pluginId, object $pluginConfig): void
    {
        // 1) Resolve hostConfig payload
        $hostConfig = $pluginConfig->hostConfig ?? null;

        if ($hostConfig === null) {
            return; // nothing to install
        }

        if (is_object($hostConfig) || is_array($hostConfig)) {
            // hostConfig is an inline object
            $hostConfigPayload = $hostConfig;
        } else {
            throw new InvalidArgumentException('hostConfig must be a string path or an object.');
        }

        // 2) Convert to insertable rows
        $rows = HostConfigToPluginSettings::makeRows($pluginId, $hostConfigPayload);

        if ($rows === []) {
            return;
        }

        // 3) Upsert into plugin_settings
        PluginSetting::query()->upsert(
            $rows,
            ['plugin_id', 'key'], // unique constraint
            [
                'label',
                'value',
                'type',
                'group',
                'is_required',
                'is_sensitive',
                'updated_at',
            ]
        );
    }
}