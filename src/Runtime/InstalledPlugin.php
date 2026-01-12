<?php
/** @noinspection PhpUnused */
declare(strict_types=1);

namespace Timeax\FortiPlugin\Runtime;

use Illuminate\Support\Str;
use InvalidArgumentException;
use JsonException;
use RuntimeException;
use Throwable;
use Timeax\FortiPlugin\Contracts\ConfigInterface;
use Timeax\FortiPlugin\Enums\PluginSettingValueType;
use Timeax\FortiPlugin\Enums\PluginStatus;
use Timeax\FortiPlugin\Enums\ProcessStatus;
use Timeax\FortiPlugin\Enums\ProcessType;
use Timeax\FortiPlugin\Installations\Lifecycle\Deactivation\DeactivationResult;
use Timeax\FortiPlugin\Installations\Lifecycle\Deactivation\Deactivator;
use Timeax\FortiPlugin\Jobs\ActivatePluginVersionJob;
use Timeax\FortiPlugin\Jobs\DeactivatePluginJob;
use Timeax\FortiPlugin\Models\Plugin;
use Timeax\FortiPlugin\Models\PluginProcess;
use Timeax\FortiPlugin\Permissions\Contracts\PermissionServiceInterface;
use Timeax\FortiPlugin\Services\PluginSettingsWriter;
use Timeax\FortiPlugin\Support\LoadedExportInfo;
use Timeax\FortiPlugin\Traits\PluginSettingsLoader;

final readonly class InstalledPlugin
{
    use PluginSettingsLoader;

    /**
     * @param class-string $configClass
     */
    public function __construct(
        private Plugin                     $plugin,
        private string                     $root,
        private string                     $configClass,
        private PluginSettingsWriter       $settingsWriter,
        private PermissionServiceInterface $permissionService,
    )
    {
        if (!is_subclass_of($configClass, ConfigInterface::class)) {
            throw new InvalidArgumentException("Config class must implement ConfigInterface");
        }
    }

    public function id(): int
    {
        return $this->plugin->id;
    }

    public function root(): string
    {
        return $this->root;
    }

    /**
     * @return class-string<ConfigInterface>
     */
    public function getConfigClass(): string
    {
        return $this->configClass;
    }

    /**
     * Resolve export info and persist the resolved metadata into Plugin->config
     * if it hasn't been persisted yet.
     *
     * Rules:
     * - If $key is null => MAIN
     * - If $key is non-null => EXPORTS (exact key lookup; dots are literal)
     */
    public function get(?string $key = null): LoadedExportInfo
    {
        // MAIN only when no parameter
        if ($key === null) {
            $persisted = $this->getPersistedMain();
            if ($persisted !== null) {
                return $persisted;
            }

            /** @var LoadedExportInfo $info */
            $info = $this->getConfigClass()::load();

            $this->persistMain($info);

            return $info;
        }

        $key = trim($key);
        if ($key === '') {
            throw new RuntimeException('Export key cannot be empty.');
        }

        // EXPORTS only when parameter is provided (exact key)
        $persisted = $this->getPersistedExport($key);
        if ($persisted !== null) {
            return $persisted;
        }

        /** @var LoadedExportInfo $info */
        $info = $this->getConfigClass()::load($key);

        $this->persistExport($key, $info);

        return $info;
    }

    private function getPersistedMain(): ?LoadedExportInfo
    {
        $cfg = (array)($this->plugin->config ?? []);
        $raw = $cfg['resolved']['files']['main'] ?? null;

        return is_array($raw) ? LoadedExportInfo::fromArray($raw) : null;
    }

    private function getPersistedExport(string $key): ?LoadedExportInfo
    {
        $cfg = (array)($this->plugin->config ?? []);
        $raw = $cfg['resolved']['files']['exports'][$key] ?? null;

        return is_array($raw) ? LoadedExportInfo::fromArray($raw) : null;
    }

    private function persistMain(LoadedExportInfo $info): void
    {
        $cfg = (array)($this->plugin->config ?? []);

        // Do not overwrite if already present
        if (isset($cfg['resolved']['files']['main'])) {
            return;
        }

        $cfg['resolved']['files']['main'] = $info->jsonSerialize();

        $this->plugin->config = $cfg;
        $this->plugin->save();
    }

    private function persistExport(string $key, LoadedExportInfo $info): void
    {
        $cfg = (array)($this->plugin->config ?? []);

        // Do not overwrite if already present
        if (isset($cfg['resolved']['files']['exports'][$key])) {
            return;
        }

        $cfg['resolved']['files']['exports'][$key] = $info->jsonSerialize();

        $this->plugin->config = $cfg;
        $this->plugin->save();
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

    /**
     * @throws Throwable
     * @throws JsonException
     */
    public function deactivate(string $id): DeactivationResult
    {
        $process = PluginProcess::create([
            'source_id' => $this->plugin->id,
            'type' => ProcessType::deactivator,
            'status' => ProcessStatus::pending,
            'run_id' => Str::uuid()->toString(),
        ]);

        DeactivatePluginJob::dispatch(
            plugin: $this->plugin,
            actor: $id,
            runId: $process->run_id,
        );

        return $process->id;
    }

    public function activate(int $id): int
    {
        $process = PluginProcess::create([
            'source_id' => $this->plugin->id,
            'type' => ProcessType::installer,
            'status' => ProcessStatus::pending,
            'run_id' => Str::uuid()->toString(),
        ]);

        ActivatePluginVersionJob::dispatch(
            pluginVersionId: $this->plugin->plugin_versions()->latest()->firstOrFail()->id,
            zipPlaceholderId: $this->plugin->plugin_placeholder_id,
            runId: $process->run_id,
            actor: (string)$id
        );

        return $process->id;
    }

    /**
     * @throws JsonException
     */
    public function initSettings(): void
    {
        $this->installHostConfig($this->id(), $this->getConfigClass()::getHostConfig());
    }
}