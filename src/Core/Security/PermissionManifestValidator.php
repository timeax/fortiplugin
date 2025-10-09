<?php /** @noinspection PhpUnnecessaryCurlyVarSyntaxInspection */
/** @noinspection PhpSameParameterValueInspection */

/** @noinspection PhpUndefinedClassInspection */

namespace Timeax\FortiPlugin\Core\Security;

use InvalidArgumentException;
use JsonException;
use Timeax\FortiPlugin\Lib\Obfuscator;
use Timeax\FortiPlugin\Permissions\Support\HostConfigNormalizer;

final class PermissionManifestValidator
{
    // inside PermissionManifestValidator class (properties section)
    private array $moduleAliasMap;      // alias => ['map' => FQCN, 'docs' => string|null]
    private array $moduleFqcnToAlias;   // FQCN => alias
    /** Canonical codec groups → method names */
    private array $codecGroups;

    /** Allowed rule types */
    private const TYPES = ['db', 'file', 'network', 'notify', 'module', 'codec'];

    /** Per-type allowed actions */
    private const ACTIONS = [
        'db' => ['select', 'insert', 'update', 'delete', 'truncate', 'transaction'],
        'file' => ['read', 'write', 'append', 'delete', 'mkdir', 'rmdir', 'list'],
        'network' => ['request'],
        'notify' => ['send'],
        'module' => ['call', 'publish', 'subscribe'],
        'codec' => ['invoke'],
    ];

    /** HTTP method allowlist */
    private const HTTP_METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'];

    /* ========================= Host-provided catalogs ========================= */

    /** @var string[] Allowed notification channels (from host config). */
    private array $allowedChannels;

    /**
     * Host DB model map (alias => ['map' => FQCN, 'relations' => array<string,string>])
     * Example entry: 'user' => ['map' => 'App\\Models\\User', 'relations' => ['posts' => 'post']]
     */
    private array $modelAliasMap;

    /** @var array<string,string> FQCN => alias (reverse lookup for convenience) */
    private array $fqcnToAlias;

    /**
     * @param string[]|null $allowedChannels If null, tries config('fortiplugin.notifications-channels', [])
     * @param array<string,array{map:string,relations?:array<string,string>}>|null $modelConfig
     *        If null, tries config('fortiplugin.models', [])
     */
    public function __construct(
        ?array $allowedChannels = null,
        ?array $modelConfig = null,
        ?array $moduleConfig = null,
        ?array $codecConfig = null,
    )
    {
        $this->allowedChannels = $this->normalizeChannels(
            $allowedChannels ?? $this->readConfig('fortiplugin-maps.notifications-channels', [])
        );

        $this->modelAliasMap = $this->normalizeModels(
            $modelConfig ?? $this->readConfig('fortiplugin-maps.models', [])
        );
        $this->fqcnToAlias = [];
        foreach ($this->modelAliasMap as $alias => $def) {
            $this->fqcnToAlias[$def['map']] = $alias;
        }

        // NEW: modules catalog
        $this->moduleAliasMap = $this->normalizeModules(
            $moduleConfig ?? $this->readConfig('fortiplugin-maps.modules', [])
        );
        $this->moduleFqcnToAlias = [];
        foreach ($this->moduleAliasMap as $alias => $def) {
            $this->moduleFqcnToAlias[$def['map']] = $alias;
        }

        $this->codecGroups = $codecConfig ?? $this->loadCodecGroups();
    }

    /**
     * Pull groups from Timeax\FortiPlugin\Lib\Obfuscator::availableGroups().
     * Obfuscator returns [group => [methodName => wrapperName]].
     * We normalize to [group => [methodName, ...]].
     */
    private function loadCodecGroups(): array
    {
        if (!class_exists(Obfuscator::class) || !method_exists(Obfuscator::class, 'availableGroups')) {
            return []; // no catalog available (e.g., during tests)
        }

        return HostConfigNormalizer::codecGroupsFromObfuscatorMap(Obfuscator::availableGroups());
    }
    /* ========================= Public API ========================= */

    /** Validate a manifest (array or JSON string). Returns normalized manifest or throws. */
    public function validate(array|string $manifest): array
    {
        $data = is_string($manifest) ? $this->decodeJson($manifest) : $manifest;

        $errors = [];
        $norm = ['required_permissions' => [], 'optional_permissions' => []];

        // Top-level shape
        if (!is_array($data)) {
            $this->boom('$.', 'manifest must be an object', $errors);
        }
        $this->rejectUnknownKeys($data, ['required_permissions', 'optional_permissions', '$schema', '$id', 'title', 'description'], '$');

        // required_permissions (required)
        if (!array_key_exists('required_permissions', $data) || !is_array($data['required_permissions'])) {
            $this->boom('$.required_permissions', 'required_permissions must be an array', $errors);
        }

        // optional_permissions (optional)
        if (isset($data['optional_permissions']) && !is_array($data['optional_permissions'])) {
            $this->boom('$.optional_permissions', 'optional_permissions must be an array if provided', $errors);
        }

        // Validate both lists
        foreach (['required_permissions', 'optional_permissions'] as $listKey) {
            if (!isset($data[$listKey]) || !is_array($data[$listKey])) {
                continue;
            }
            foreach (array_values($data[$listKey]) as $i => $rule) {
                $path = '$.' . $listKey . '[' . $i . ']';
                $norm[$listKey][] = $this->validateRule($rule, $path, $errors);
            }
        }

        if ($errors) {
            $msg = "Permission manifest validation failed:\n- " . implode("\n- ", $errors);
            throw new InvalidArgumentException($msg);
        }

        return $norm;
    }

    /* ========================= Rule validators ========================= */

    private function validateRule(mixed $rule, string $path, array &$errors): array
    {
        if (!is_array($rule)) {
            $this->boom($path, 'rule must be an object', $errors);
            return [];
        }

        $this->rejectUnknownKeys($rule, ['type', 'target', 'actions', 'conditions', 'audit', 'justification', 'methods', 'groups', 'options'], $path);

        // common fields
        $type = $rule['type'] ?? null;
        if (!is_string($type) || !in_array($type, self::TYPES, true)) {
            $this->boom("{$path}.type", 'type must be one of: ' . implode(',', self::TYPES), $errors);
        }

        $actions = $rule['actions'] ?? null;
        if (!is_array($actions) || $actions === []) {
            $this->boom("{$path}.actions", 'actions must be a non-empty array', $errors);
        } else {
            $actions = array_values(array_unique(array_map('strval', $actions)));
            $allowed = self::ACTIONS[$type] ?? [];
            foreach ($actions as $a) {
                if (!in_array($a, $allowed, true)) {
                    $this->boom("{$path}.actions", "action '{$a}' not allowed for type '{$type}'", $errors);
                }
            }
        }

        // audit (optional)
        if (isset($rule['audit'])) {
            $this->validateAudit($rule['audit'], "{$path}.audit", $errors);
        }

        // conditions (optional; only setting_link, guard, env)
        $condNorm = null;
        if (isset($rule['conditions'])) {
            $condNorm = $this->validateConditions($rule['conditions'], "{$path}.conditions", $errors);
        }

        // per-type target + extras
        $target = $rule['target'] ?? null;
        $normalized = [
            'type' => $type,
            'target' => null,
            'actions' => $actions ?? [],
            'conditions' => $condNorm,
            'audit' => $rule['audit'] ?? null,
            'justification' => isset($rule['justification']) ? (string)$rule['justification'] : null,
        ];

        switch ($type) {
            case 'db':
                $normalized['target'] = $this->validateDbTarget($target, "{$path}.target", $errors, $actions);
                break;

            case 'file':
                $normalized['target'] = $this->validateFileTarget($target, "{$path}.target", $errors);
                break;

            case 'network':
                $normalized['target'] = $this->validateNetworkTarget($target, "{$path}.target", $errors);
                break;

            case 'notify':
                $normalized['target'] = $this->validateNotifyTarget($target, "{$path}.target", $errors);
                break;

            case 'module':
                $normalized['target'] = $this->validateModuleTarget($target, "{$path}.target", $errors);
                break;

            case 'codec':
                $normalized['target'] = $this->validateCodecTarget($target, "{$path}.target", $errors);
                [$resolved, $requiresGuard] = $this->validateCodecMethodsAndGroups(
                    $rule['methods'] ?? null,
                    $rule['groups'] ?? null,
                    $rule['options'] ?? null,
                    ($path)
                    , $errors);

                $normalized['methods'] = $rule['methods'] ?? null;
                $normalized['groups'] = $rule['groups'] ?? null;
                $normalized['options'] = $rule['options'] ?? null;
                $normalized['resolved_methods'] = $resolved;
                $normalized['requires_unserialize_guard'] = $requiresGuard;
                break;
        }

        return $normalized;
    }

    /* ========================= Type: DB ========================= */

    private function validateDbTarget(mixed $target, string $path, array &$errors, array $actions): ?array
    {
        if (!is_array($target)) {
            $this->boom($path, 'target must be an object', $errors);
            return null;
        }
        $hasModel = array_key_exists('model', $target);
        $hasTable = array_key_exists('table', $target);
        if ($hasModel === $hasTable) {
            $this->boom($path, "exactly one of 'model' or 'table' is required", $errors);
        }

        $modelFqcn = null;
        $modelAlias = null;
        $hostColsAll = null;
        $hostColsWritable = null;

        if ($hasModel) {
            $decl = $target['model'];
            if (!is_string($decl) || $decl === '') {
                $this->boom("{$path}.model", 'model must be a non-empty string (alias or FQCN)', $errors);
            } else if (array_key_exists($decl, $this->modelAliasMap)) {
                $modelAlias = $decl;
                $modelFqcn = $this->modelAliasMap[$decl]['map'];
                $hostColsAll = $this->modelAliasMap[$decl]['columns']['all'] ?? null;
                $hostColsWritable = $this->modelAliasMap[$decl]['columns']['writable'] ?? null;
            } else if (array_key_exists($decl, $this->fqcnToAlias)) {
                $modelFqcn = $decl;
                $modelAlias = $this->fqcnToAlias[$decl];
                $hostColsAll = $this->modelAliasMap[$modelAlias]['columns']['all'] ?? null;
                $hostColsWritable = $this->modelAliasMap[$modelAlias]['columns']['writable'] ?? null;
            } else if ($this->modelAliasMap !== []) {
                $this->boom("{$path}.model", "unknown model alias/FQCN '{$decl}' (not in host 'models' map)", $errors);
            } else {
                $modelFqcn = $decl;
            }
        }

        if ($hasTable && (!is_string($target['table']) || $target['table'] === '')) {
            $this->boom("{$path}.table", 'table must be a non-empty string', $errors);
        }

        // columns in manifest (optional)
        $cols = null;
        if (isset($target['columns'])) {
            if (!$this->isStringList($target['columns'])) {
                $this->boom("{$path}.columns", 'columns must be an array of unique strings', $errors);
            } else {
                $cols = array_values(array_unique(array_map('strval', $target['columns'])));
            }
        }

        $this->rejectUnknownKeys($target, ['model', 'table', 'columns'], $path);

        // Enforce host column policy if present and a model is known
        if ($modelAlias !== null) {
            $hasWrite = (bool)array_intersect($actions, ['insert', 'update']);

            if ($cols !== null) {
                // If write actions requested, require ⊆ writable (when known); else require ⊆ all (when known)
                if ($hasWrite && $hostColsWritable !== null) {
                    $diff = array_diff($cols, $hostColsWritable);
                    if ($diff) {
                        $this->boom("{$path}.columns", "columns not writable by host policy: " . implode(', ', $diff), $errors);
                    }
                }
                if ($hostColsAll !== null) {
                    $diffAll = array_diff($cols, $hostColsAll);
                    if ($diffAll) {
                        $this->boom("{$path}.columns", "columns not allowed by host policy: " . implode(', ', $diffAll), $errors);
                    }
                }
            }
        }

        return [
            'model' => $modelFqcn,
            'model_alias' => $modelAlias,
            'table' => $hasTable ? $target['table'] : null,
            'columns' => $cols,
        ];
    }

    /* ========================= Type: FILE ========================= */

    private function validateFileTarget(mixed $target, string $path, array &$errors): ?array
    {
        if (!is_array($target)) {
            $this->boom($path, 'target must be an object', $errors);
            return null;
        }
        foreach (['base_dir', 'paths'] as $req) {
            if (!array_key_exists($req, $target)) {
                $this->boom("{$path}.{$req}", "{$req} is required", $errors);
            }
        }
        if (!is_string($target['base_dir'] ?? null) || $target['base_dir'] === '') {
            $this->boom("{$path}.base_dir", 'base_dir must be a non-empty string', $errors);
        }
        if (!$this->isStringList($target['paths'] ?? null, true)) {
            $this->boom("{$path}.paths", 'paths must be a non-empty array of unique strings', $errors);
        }
        foreach ($target['paths'] ?? [] as $idx => $p) {
            if (str_contains($p, '..')) {
                $this->boom("{$path}.paths[{$idx}]", "path must not contain '..'", $errors);
            }
        }
        if (isset($target['follow_symlinks']) && !is_bool($target['follow_symlinks'])) {
            $this->boom("{$path}.follow_symlinks", 'follow_symlinks must be boolean', $errors);
        }
        $this->rejectUnknownKeys($target, ['base_dir', 'paths', 'follow_symlinks'], $path);

        return [
            'base_dir' => (string)$target['base_dir'],
            'paths' => array_values(array_unique(array_map('strval', $target['paths']))),
            'follow_symlinks' => (bool)($target['follow_symlinks'] ?? false),
        ];
    }

    /* ========================= Type: NETWORK ========================= */

    private function validateNetworkTarget(mixed $target, string $path, array &$errors): ?array
    {
        if (!is_array($target)) {
            $this->boom($path, 'target must be an object', $errors);
            return null;
        }
        foreach (['hosts', 'methods'] as $req) {
            if (!array_key_exists($req, $target)) {
                $this->boom("{$path}.{$req}", "{$req} is required", $errors);
            }
        }
        if (!$this->isStringList($target['hosts'] ?? null, true)) {
            $this->boom("{$path}.hosts", 'hosts must be a non-empty array of strings', $errors);
        } else {
            foreach ($target['hosts'] as $i => $h) {
                if (!preg_match('/^([a-z0-9.-]+|\*\.[a-z0-9.-]+)$/', $h)) {
                    $this->boom("{$path}.hosts[{$i}]", "invalid host pattern '{$h}'", $errors);
                }
            }
        }
        if (!$this->isStringList($target['methods'] ?? null, true)) {
            $this->boom("{$path}.methods", 'methods must be a non-empty array of strings', $errors);
        } else {
            foreach ($target['methods'] as $i => $m) {
                if (!in_array(strtoupper($m), self::HTTP_METHODS, true)) {
                    $this->boom("{$path}.methods[{$i}]", "method '{$m}' not allowed", $errors);
                }
            }
        }
        if (isset($target['schemes'])) {
            if (!$this->isStringList($target['schemes'])) {
                $this->boom("{$path}.schemes", 'schemes must be an array of strings', $errors);
            } else {
                foreach ($target['schemes'] as $i => $s) {
                    if (!in_array($s, ['https', 'http'], true)) {
                        $this->boom("{$path}.schemes[{$i}]", "scheme '{$s}' not allowed", $errors);
                    }
                }
            }
        }
        if (isset($target['ports'])) {
            if (!is_array($target['ports'])) {
                $this->boom("{$path}.ports", 'ports must be an array of integers', $errors);
            } else {
                foreach ($target['ports'] as $i => $p) {
                    if (!is_int($p) || $p < 1 || $p > 65535) {
                        $this->boom("{$path}.ports[{$i}]", 'port must be an integer between 1 and 65535', $errors);
                    }
                }
            }
        }
        if (isset($target['ips_allowed'])) {
            if (!$this->isStringList($target['ips_allowed'])) {
                $this->boom("{$path}.ips_allowed", 'ips_allowed must be an array of strings', $errors);
            } else {
                foreach ($target['ips_allowed'] as $i => $ip) {
                    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                        $this->boom("{$path}.ips_allowed[{$i}]", "invalid IP '{$ip}'", $errors);
                    }
                }
            }
        }
        if (isset($target['headers_allowed']) && !$this->isStringList($target['headers_allowed'])) {
            $this->boom("{$path}.headers_allowed", 'headers_allowed must be an array of strings', $errors);
        }
        if (isset($target['paths']) && !$this->isStringList($target['paths'])) {
            $this->boom("{$path}.paths", 'paths must be an array of strings', $errors);
        }
        if (isset($target['auth_via_host_secret']) && !is_bool($target['auth_via_host_secret'])) {
            $this->boom("{$path}.auth_via_host_secret", 'auth_via_host_secret must be boolean', $errors);
        }

        $this->rejectUnknownKeys($target, [
            'hosts', 'schemes', 'ports', 'paths', 'methods', 'headers_allowed', 'auth_via_host_secret', 'ips_allowed'
        ], $path);

        return [
            'hosts' => array_values(array_unique(array_map('strval', $target['hosts']))),
            'schemes' => isset($target['schemes']) ? array_values(array_unique(array_map('strval', $target['schemes']))) : null,
            'ports' => isset($target['ports']) ? array_values($target['ports']) : null,
            'paths' => isset($target['paths']) ? array_values(array_unique(array_map('strval', $target['paths']))) : null,
            'methods' => array_values(array_unique(array_map(static fn($m) => strtoupper($m), $target['methods']))),
            'headers_allowed' => isset($target['headers_allowed']) ? array_values(array_unique(array_map('strval', $target['headers_allowed']))) : null,
            'auth_via_host_secret' => (bool)($target['auth_via_host_secret'] ?? true),
            'ips_allowed' => isset($target['ips_allowed']) ? array_values(array_unique(array_map('strval', $target['ips_allowed']))) : null,
        ];
    }

    /* ========================= Type: NOTIFY ========================= */

    private function validateNotifyTarget(mixed $target, string $path, array &$errors): ?array
    {
        if (!is_array($target)) {
            $this->boom($path, 'target must be an object', $errors);
            return null;
        }
        if (!$this->isStringList($target['channels'] ?? null, true)) {
            $this->boom("{$path}.channels", 'channels must be a non-empty array of strings', $errors);
        } else if ($this->allowedChannels !== []) {
            foreach ($target['channels'] as $i => $c) {
                if (!in_array($c, $this->allowedChannels, true)) {
                    $this->boom("{$path}.channels[{$i}]", "channel '{$c}' is not allowed by host", $errors);
                }
            }
        }
        if (isset($target['templates']) && !$this->isStringList($target['templates'])) {
            $this->boom("{$path}.templates", 'templates must be an array of strings', $errors);
        }
        if (isset($target['recipients']) && !$this->isStringList($target['recipients'])) {
            $this->boom("{$path}.recipients", 'recipients must be an array of strings', $errors);
        }
        $this->rejectUnknownKeys($target, ['channels', 'templates', 'recipients'], $path);

        return [
            'channels' => array_values(array_unique(array_map('strval', $target['channels']))),
            'templates' => isset($target['templates']) ? array_values(array_unique(array_map('strval', $target['templates']))) : null,
            'recipients' => isset($target['recipients']) ? array_values(array_unique(array_map('strval', $target['recipients']))) : null,
        ];
    }

    /* ========================= Type: MODULE ========================= */

    private function validateModuleTarget(mixed $target, string $path, array &$errors): ?array
    {
        if (!is_array($target)) {
            $this->boom($path, 'target must be an object', $errors);
            return null;
        }

        foreach (['plugin', 'apis'] as $req) {
            if (!array_key_exists($req, $target)) {
                $this->boom("{$path}.{$req}", "{$req} is required", $errors);
            }
        }

        // plugin must be a non-empty string
        $pluginDecl = $target['plugin'] ?? null;
        if (!is_string($pluginDecl) || $pluginDecl === '') {
            $this->boom("{$path}.plugin", 'plugin must be a non-empty string', $errors);
        }

        // apis must be a non-empty list of strings
        if (!$this->isStringList($target['apis'] ?? null, true)) {
            $this->boom("{$path}.apis", 'apis must be a non-empty array of strings', $errors);
        }

        $this->rejectUnknownKeys($target, ['plugin', 'apis'], $path);

        // ---- Host catalog check (alias or FQCN) ----
        $alias = null;
        $fqcn = null;
        $docs = null;

        if (array_key_exists($pluginDecl, $this->moduleAliasMap)) {
            // Declared as alias
            $alias = $pluginDecl;
            $fqcn = $this->moduleAliasMap[$alias]['map'];
            $docs = $this->moduleAliasMap[$alias]['docs'];
        } elseif (array_key_exists($pluginDecl, $this->moduleFqcnToAlias)) {
            // Declared as FQCN
            $fqcn = $pluginDecl;
            $alias = $this->moduleFqcnToAlias[$fqcn];
            $docs = $this->moduleAliasMap[$alias]['docs'] ?? null;
        } else if ($this->moduleAliasMap !== []) {
            $this->boom("{$path}.plugin", "unknown module '{$pluginDecl}' (not in host modules map)", $errors);
        } else {
            // No catalog -> accept free-form, but no alias/docs
            $fqcn = $pluginDecl;
        }

        return [
            'plugin' => (string)$pluginDecl,                                      // as declared
            'plugin_alias' => $alias,                                                   // normalized alias (if known)
            'plugin_fqcn' => $fqcn,                                                    // normalized FQCN (if known or free-form)
            'plugin_docs' => $docs,                                                    // host docs URL (if any)
            'apis' => array_values(array_unique(array_map('strval', $target['apis']))),
        ];
    }

    /* ========================= Type: CODEC ========================= */

    private function validateCodecTarget(mixed $target, string $path, array &$errors): ?string
    {
        if (!is_string($target)) {
            $this->boom($path, 'target must be the string "codec"', $errors);
            return null;
        }
        if ($target !== 'codec') {
            $this->boom($path, 'codec rule target must be "codec"', $errors);
        }
        return 'codec';
    }

    /**
     * Validate codec methods/groups + options, and return [resolved_methods, requires_unserialize_guard].
     */
    private function validateCodecMethodsAndGroups(mixed $methods, mixed $groups, mixed $options, string $path, array &$errors): array
    {
        $resolved = [];
        $needsGuard = false;

        // groups (optional)
        if ($groups !== null) {
            if (!$this->isStringList($groups)) {
                $this->boom("{$path}.groups", 'groups must be an array of strings', $errors);
            } else {
                foreach ($groups as $i => $g) {
                    if (!isset($this->codecGroups[$g])) {
                        $this->boom("{$path}.groups[{$i}]", "unknown codec group '{$g}'", $errors);
                        continue;
                    }

                    // If the group includes 'unserialize', require allowlist options
                    if (in_array('unserialize', $this->codecGroups[$g], true)) {
                        $needsGuard = true;
                    }

                    // Append methods directly (avoid array_merge in loop)
                    foreach ($this->codecGroups[$g] as $method) {
                        $resolved[] = $method;
                    }
                }
            }
        }

        // methods (optional | "*" | array)
        $wildcard = false;
        if ($methods !== null) {
            if ($methods === '*') {
                $wildcard = true;
                $needsGuard = true; // wildcard includes 'unserialize'
            } elseif ($this->isStringList($methods)) {
                foreach ($methods as $i => $m) {
                    if (!preg_match('/^[a-z0-9_]+$/', $m)) {
                        $this->boom("{$path}.methods[{$i}]", "invalid method name '{$m}'", $errors);
                    }
                    if ($m === 'unserialize') {
                        $needsGuard = true;
                    }
                    $resolved[] = $m;
                }
            } else {
                $this->boom("{$path}.methods", 'methods must be "*" or an array of strings', $errors);
            }
        }

        if ($methods === null && $groups === null) {
            $this->boom($path, 'codec rule requires one of: methods or groups', $errors);
        }

        // options (required if guard is needed)
        if ($needsGuard) {
            if (!is_array($options) || !array_key_exists('allow_unserialize_classes', $options)) {
                $this->boom("{$path}.options", 'options.allow_unserialize_classes is required when methods="*" or includes "unserialize", or groups include "serialize"', $errors);
            } else if (!is_array($options['allow_unserialize_classes'])) {
                $this->boom("{$path}.options.allow_unserialize_classes", 'must be an array (empty array = no classes allowed)', $errors);
            } else {
                foreach ($options['allow_unserialize_classes'] as $i => $cls) {
                    if (!is_string($cls) || $cls === '') {
                        $this->boom("{$path}.options.allow_unserialize_classes[{$i}]", 'class name must be a non-empty string', $errors);
                    }
                }
            }
        } elseif ($options !== null) {
            $this->rejectUnknownKeys($options, ['allow_unserialize_classes'], "{$path}.options");
        }

        // Normalize resolved methods
        if ($wildcard) {
            $resolved = '*';
        } else {
            $resolved = array_values(array_unique($resolved));
        }

        return [$resolved, $needsGuard];
    }

    /* ========================= Common validators ========================= */

    private function validateAudit(mixed $audit, string $path, array &$errors): void
    {
        if (!is_array($audit)) {
            $this->boom($path, 'audit must be an object', $errors);
            return;
        }
        $this->rejectUnknownKeys($audit, ['log', 'redact_fields', 'tags'], $path);
        if (isset($audit['log']) && !in_array($audit['log'], ['always', 'on_deny', 'never'], true)) {
            $this->boom("{$path}.log", "log must be 'always', 'on_deny' or 'never'", $errors);
        }
        if (isset($audit['redact_fields']) && !$this->isStringList($audit['redact_fields'])) {
            $this->boom("{$path}.redact_fields", 'redact_fields must be an array of strings', $errors);
        }
        if (isset($audit['tags']) && !$this->isStringList($audit['tags'])) {
            $this->boom("{$path}.tags", 'tags must be an array of strings', $errors);
        }
    }

    private function validateConditions(mixed $cond, string $path, array &$errors): ?array
    {
        if (!is_array($cond)) {
            $this->boom($path, 'conditions must be an object', $errors);
            return null;
        }
        $this->rejectUnknownKeys($cond, ['setting_link', 'guard', 'env'], $path);

        $out = ['setting_link' => null, 'guard' => null, 'env' => null];

        if (array_key_exists('setting_link', $cond)) {
            $v = $cond['setting_link'];
            if (!is_string($v) && !is_int($v) && !is_float($v)) {
                $this->boom("{$path}.setting_link", 'setting_link must be a string or number', $errors);
            } else {
                $out['setting_link'] = $v;
            }
        }
        if (array_key_exists('guard', $cond)) {
            if (!is_string($cond['guard']) || $cond['guard'] === '') {
                $this->boom("{$path}.guard", 'guard must be a non-empty string', $errors);
            } else {
                $out['guard'] = $cond['guard'];
            }
        }
        if (array_key_exists('env', $cond)) {
            $env = $cond['env'];
            if (!is_array($env)) {
                $this->boom("{$path}.env", 'env must be an object with allow/deny arrays', $errors);
            } else {
                $this->rejectUnknownKeys($env, ['allow', 'deny'], "{$path}.env");
                foreach (['allow', 'deny'] as $k) {
                    if (isset($env[$k]) && !$this->isStringList($env[$k])) {
                        $this->boom("{$path}.env.{$k}", "{$k} must be an array of strings", $errors);
                    }
                }
                $out['env'] = [
                    'allow' => isset($env['allow']) ? array_values(array_unique(array_map('strval', $env['allow']))) : null,
                    'deny' => isset($env['deny']) ? array_values(array_unique(array_map('strval', $env['deny']))) : null,
                ];
            }
        }

        return $out;
    }

    /* ========================= Helpers ========================= */

    private function decodeJson(string $json): array
    {
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidArgumentException('Invalid JSON: ' . $e->getMessage(), 0, $e);
        }
        if (!is_array($data)) {
            throw new InvalidArgumentException('Manifest root must decode to an object');
        }
        return $data;
    }

    private function isStringList(mixed $v, bool $nonEmpty = false): bool
    {
        if (!is_array($v)) return false;
        if ($nonEmpty && $v === []) return false;
        foreach ($v as $s) {
            if (!is_string($s)) return false;
        }
        return count($v) === count(array_unique($v));
    }

    private function rejectUnknownKeys(array $obj, array $allowed, string $path): void
    {
        $unknown = array_diff(array_keys($obj), $allowed);
        if ($unknown) {
            $keys = implode(', ', $unknown);
            throw new InvalidArgumentException("{$path}: unknown field(s): {$keys}");
        }
    }

    private function boom(string $path, string $msg, array &$errors): void
    {
        $errors[] = "{$path}: {$msg}";
    }

    /** Config helper that safely no-ops outside Laravel. */
    private function readConfig(string $key, mixed $default): mixed
    {
        if (function_exists('config')) {
            return config($key, $default);
        }
        return $default;
    }

    /**
     * Normalize channels list: supports ['email','sms'] or ['email'=>true,'sms'=>true].
     * Returns a unique list of strings.
     */
    private function normalizeChannels(array $channels): array
    {
        return HostConfigNormalizer::notificationChannels($channels);
    }

    /**
     * Normalize models map. Ensures 'map' (FQCN), optional 'relations', and optional
     * 'columns' policy with 'all' and 'writable' (writable ⊆ all).
     * @param array<string,mixed> $models
     * @return array<string,array{map:string,relations:array<string,string>,columns:array{all?:array,writable?:array}}>
     */
    private function normalizeModels(array $models): array
    {
        return HostConfigNormalizer::models($models);
    }

    /**
     * Normalize modules map. Ensures 'map' (FQCN) exists; 'docs' optional string.
     * @param array<string,mixed> $modules
     * @return array<string,array{map:string,docs:?string}>
     */
    private function normalizeModules(array $modules): array
    {
        return HostConfigNormalizer::modules($modules);
    }
}