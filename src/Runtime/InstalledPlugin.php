<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Runtime;

use Timeax\FortiPlugin\Enums\PluginSettingValueType;
use Timeax\FortiPlugin\Models\Plugin;
use Timeax\FortiPlugin\Permissions\Contracts\PermissionServiceInterface;
use Timeax\FortiPlugin\Services\PluginSettingsWriter;

final readonly class InstalledPlugin
{

    /**
     * @param class-string $configClass
     */
    public function __construct(
        private Plugin $plugin,
        private string $root,
        private string $configClass,
        private PluginSettingsWriter $settingsWriter,
        private PermissionServiceInterface $permissionService,
    ) {}

    public function id(): int
    {
        return $this->plugin->id;
    }

    public function root(): string
    {
        return $this->root;
    }

    /**
     * @return class-string
     */
    public function configClass(): string
    {
        return $this->configClass;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $cls = $this->configClass;

        if (method_exists($cls, 'get')) {

            return $cls::get($key, $default);
        }

        return $default;
    }

    public function getSetting(string $key, mixed $default = null): mixed
    {
        $setting = $this->plugin->plugin_settings()
            ->select(['key', 'value', 'type'])
            ->where('key', $key)
            ->first();

        if (!$setting) {
            return $default;
        }

        return PluginSettingsWriter::decodeValue($setting->type, $setting->value, $default);
    }

    public function updateSetting(string $key, mixed $value, ?PluginSettingValueType $type = null): void
    {
        $this->settingsWriter->set($this->id(), $key, $value, $type);
    }

    public function updateSettings(array $kv, ?array $types = null): void
    {
        $this->settingsWriter->setMany($this->id(), $kv, $types);
    }


    public function permissions(): PluginPermissions
    {
        return new PluginPermissions(
            $this->id(),
            $this->permissionService
        );
    }
}
