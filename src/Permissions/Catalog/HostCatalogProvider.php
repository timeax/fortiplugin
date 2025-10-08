<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Catalog;

use JsonException;
use Throwable;
use Timeax\FortiPlugin\Permissions\Cache\KeyBuilder;
use Timeax\FortiPlugin\Permissions\Contracts\CatalogProviderInterface;

/**
 * Host-backed catalog provider.
 *
 * Responsibilities:
 *  - Compose model/module/notification/codec catalogs.
 *  - Provide normalized snapshots to validators/ingestors.
 *  - Expose stable revision markers to support capability cache ETAGs.
 */
final readonly class HostCatalogProvider implements CatalogProviderInterface
{
    public function __construct(
        private ModelCatalog        $models  = new ModelCatalog(),
        private ModuleCatalog       $modules = new ModuleCatalog(),
        private NotificationCatalog $notify  = new NotificationCatalog(),
        private CodecCatalog        $codec   = new CodecCatalog(),
    ) {}

    /**
     * Alias → ['map'=>FQCN, 'relations'=>alias map, 'columns'=>['all'=>?string[],'writable'=>?string[]]]
     * Shape matches what your Core PermissionManifestValidator expects.
     * @return array<string,array{map:string,relations:array<string,string>,columns:array{all?:array,writable?:array}}>
     */
    public function models(): array
    {
        return $this->models->all();
    }

    /**
     * Alias → ['map'=>FQCN, 'docs'=>?string]
     * @return array<string,array{map:string,docs?:string}>
     */
    public function modules(): array
    {
        return $this->modules->all();
    }

    /** @return string[] */
    public function notificationChannels(): array
    {
        return $this->notify->channels();
    }

    /** @return array<string,string[]> group => phpFunctionName[] */
    public function codecGroups(): array
    {
        return $this->codec->groups();
    }

    /** Reverse lookup helpers (optional convenience) */
    public function modelAliasForFqcn(string $fqcn): ?string
    {
        return $this->models->aliasForFqcn($fqcn);
    }

    public function moduleAliasForFqcn(string $fqcn): ?string
    {
        return $this->modules->aliasForFqcn($fqcn);
    }

    /**
     * Individual revision markers for each catalog.
     * @return array{models:string,modules:string,notify:string,codec:string}
     * @throws JsonException
     */
    public function revisions(): array
    {
        return [
            'models' => $this->models->revision(),
            'modules'=> $this->modules->revision(),
            'notify' => $this->notify->revision(),
            'codec'  => $this->codec->revision(),
        ];
    }

    /**
     * Composite revision — stable hash over all catalog revisions.
     * Useful as a single ETag for “catalog state”.
     * @throws JsonException
     * @throws JsonException
     */
    public function compositeRevision(): string
    {
        return KeyBuilder::fromCapabilities($this->revisions());
    }

    /** Snapshot (useful for diagnostics)
     * @throws JsonException
     */
    public function toArray(): array
    {
        return [
            'models'  => $this->models(),
            'modules' => $this->modules(),
            'notify'  => $this->notificationChannels(),
            'codec'   => $this->codecGroups(),
            'rev'     => $this->revisions(),
        ];
    }

    public function env(): string
    {
        // Prefer Laravel app()->environment()
        if (function_exists('app')) {
            try {
                // app()->environment() returns the current environment name (e.g., 'local', 'staging', 'production')
                $env = app()->environment();
                if (is_string($env) && $env !== '') {
                    return $env;
                }
            } catch (Throwable) {
                // fall through
            }
        }

        // Next, try config('app.env')
        if (function_exists('config')) {
            $cfg = config('app.env');
            if (is_string($cfg) && $cfg !== '') {
                return $cfg;
            }
        }

        // Then, try environment variable
        $raw = getenv('APP_ENV');
        if (is_string($raw) && $raw !== '') {
            return $raw;
        }

        // Default
        return 'production';
    }

    // Inside your HostCatalogProvider (or wherever you implemented env()/settingsForPlugin())

    public function settingsForPlugin(int $pluginId): array
    {
        $modelClass = '\\Timeax\\FortiPlugin\\Models\\PluginSetting';

        if (class_exists($modelClass)) {
            try {
                // Pull key, value, type from DB
                $rows = $modelClass::query()
                    ->where('plugin_id', $pluginId)
                    ->get(['key', 'value', 'type']);

                $map = [];
                foreach ($rows as $row) {
                    $k = (string) ($row->key ?? '');
                    if ($k === '') {
                        continue;
                    }
                    $raw  = (string) ($row->value ?? '');
                    $type = is_string($row->type ?? null) ? (string)$row->type : 'string';
                    $map[$k] = $this->decodeTypedSetting($raw, $type);
                }
                return $map;
            } catch (Throwable) {
                // fall back to config if DB path fails
            }
        }

        // Fallback: host-injected settings via config
        if (function_exists('config')) {
            $cfg = config("fortiplugin.plugin_settings.{$pluginId}", []);
            return is_array($cfg) ? $cfg : [];
        }

        return [];
    }

    /**
     * Decode a PluginSetting value using the PluginSettingValueType enum.
     *
     * @param string $value Raw DB string
     * @param string $type  'string'|'number'|'boolean'|'json'|'file'|'blob'
     * @return mixed
     */
    private function decodeTypedSetting(string $value, string $type): mixed
    {
        switch ($type) {
            case 'string':
                return $value;

            case 'number':
                // Int if safe; else float (supports scientific notation)
                if (preg_match('/^-?\d+$/', $value)) {
                    // within PHP int range?
                    // If it's too big, (int) will still be fine on 64-bit; on 32-bit it may overflow.
                    return (int) $value;
                }
                if (is_numeric($value)) {
                    return (float) $value;
                }
                // Fallback: leave as string if it's not numeric
                return $value;

            case 'boolean': {
                $v = strtolower(trim($value));
                if (in_array($v, ['1','true','yes','on'], true))  return true;
                if (in_array($v, ['0','false','no','off',''], true)) return false;
                // Fallback: non-empty → true (conservative), but you can tighten this if you prefer strict parsing.
                return (bool) $value;
            }

            case 'json':
                try {
                    return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
                } catch (Throwable) {
                    // Corrupt JSON? Return raw string so callers can decide what to do.
                    return $value;
                }

            case 'file':
                // Return the stored handle/path. Resolution (e.g. Storage::url) is a separate concern.
                return $value;

            case 'blob':
                // If you store base64 for safety, support an explicit prefix convention.
                // Example stored format: "base64:AAAA..."
                if (str_starts_with($value, 'base64:')) {
                    $b64 = substr($value, 7);
                    $bin = base64_decode($b64, true);
                    return $bin === false ? $value : $bin; // fallback to raw if invalid base64
                }
                // Otherwise return the raw string (binary-safe in PHP).
                return $value;

            default:
                // Unknown type → return as-is
                return $value;
        }
    }
}