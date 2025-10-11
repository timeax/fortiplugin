<?php

namespace Timeax\FortiPlugin\Services;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use JsonException;
use RuntimeException;
use Timeax\FortiPlugin\Core\PluginPolicy;

final readonly class PolicyService
{
    public function __construct(private Filesystem $fs)
    {
    }

    /** Canonical policy snapshot (normalized)
     * @throws JsonException|FileNotFoundException
     */
    public function snapshot(): array
    {
        $raw = $this->loadRaw();        // array in legacy or new shape
        $snap = $this->normalize($raw); // canonical shape for API
        return $this->ensureVersion($snap);
    }

    /**
     * @throws FileNotFoundException
     * @throws JsonException
     */
    public function version(): string
    {
        return (string)Arr::get($this->snapshot(), 'version', '1');
    }

    /**
     * @throws JsonException|FileNotFoundException
     */
    public function hash(): string
    {
        return hash('sha256', json_encode($this->snapshot(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @throws FileNotFoundException
     * @throws JsonException
     */
    public function makePolicy(): PluginPolicy
    {
        return new PluginPolicy($this->snapshot());
    }

    // ---------------------------------------------------------------------
    // Loading
    // ---------------------------------------------------------------------

    /**
     * @throws FileNotFoundException
     * @throws JsonException
     */
    private function loadRaw(): array
    {
        // 1) Inline config (already a PHP array under config/fortiplugin.php)
        $cfg = config('fortiplugin.policy');
        if (is_array($cfg)) {
            return $cfg;
        }

        // 2) Legacy PHP file that returns an array (exactly the shape you pasted)
        $phpPath = (string)config('fortiplugin.policy_php_path', '');
        if ($phpPath !== '' && $this->fs->exists($phpPath)) {
            $arr = include $phpPath;
            if (!is_array($arr)) {
                throw new RuntimeException("Policy PHP file did not return an array: $phpPath");
            }
            return $arr;
        }

        // 3) JSON file
        $jsonPath = (string)config('fortiplugin.policy_path', '');
        if ($jsonPath !== '' && $this->fs->exists($jsonPath)) {
            $json = json_decode($this->fs->get($jsonPath), true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($json)) {
                throw new RuntimeException("Policy JSON invalid: $jsonPath");
            }
            return $json;
        }

        // 4) Fallback defaults
        return $this->fallback();
    }

    // ---------------------------------------------------------------------
    // Normalization (legacy → canonical)
    // ---------------------------------------------------------------------

    private function normalize(array $raw): array
    {
        // Legacy detection: top-level 'validator' array
        $legacy = is_array(Arr::get($raw, 'validator'));

        if (!$legacy) {
            // Assume it's already canonical; still ensure consistent keys
            return $this->canonicalize($raw);
        }

        // Map legacy keys to canonical structure
        $validator = (array)$raw['validator'];

        $canonical = [
            // Host loader & layout
            'directory' => Arr::get($raw, 'directory', 'Plugins'),
            'loader' => Arr::get($raw, 'loader', 'default'),
            'stack_depth' => Arr::get($raw, 'stack_depth', 1),

            // Scanner rules (flattened from validator.*)
            'tokens' => array_values(array_unique((array)Arr::get($validator, 'tokens', []))),
            'ignore' => array_values((array)Arr::get($validator, 'ignore', [])),
            'whitelist' => array_values((array)Arr::get($validator, 'whitelist', [])),
            'blocklist' => (array)Arr::get($validator, 'blocklist', []),
            'dangerous_functions' => array_values(array_unique((array)Arr::get($validator, 'dangerous_functions', []))),
            'scan_size' => (array)Arr::get($validator, 'scan_size', ['php' => 5000000]),
            'max_flagged' => (int)Arr::get($validator, 'max_flagged', 5),

            // Compliance/Admin knobs (pass through)
            'security' => [
                'must_kyc' => (bool)Arr::get($raw, 'must_kyc', false),
            ],
            'publishing' => [
                'max_token_lifetime_days' => (int)Arr::get($raw, 'max_token_lifetime_days', 30),
                'allow_public_plugins' => (bool)Arr::get($raw, 'allow_public_plugins', false),
                'require_plugin_review' => (bool)Arr::get($raw, 'require_plugin_review', true),
            ],
            'admin' => [
                'allow_admin_override' => (bool)Arr::get($raw, 'allow_admin_override', true),
            ],

            // Preserve full legacy blob for traceability
            '_legacy' => $raw,
        ];

        // Ensure no duplicates in danger lists
        $canonical['dangerous_functions'] = array_values(array_unique($canonical['dangerous_functions']));
        $canonical['tokens'] = array_values(array_unique($canonical['tokens']));

        return $canonical;
    }

    /** If caller already provides canonical shape, ensure keys & defaults. */
    private function canonicalize(array $p): array
    {
        $p['directory'] = $p['directory'] ?? 'Plugins';
        $p['loader'] = $p['loader'] ?? 'default';
        $p['stack_depth'] = $p['stack_depth'] ?? 1;
        $p['tokens'] = array_values(array_unique((array)($p['tokens'] ?? [])));
        $p['ignore'] = array_values((array)($p['ignore'] ?? []));
        $p['whitelist'] = array_values((array)($p['whitelist'] ?? []));
        $p['blocklist'] = (array)($p['blocklist'] ?? []);
        $p['dangerous_functions'] = array_values(array_unique((array)($p['dangerous_functions'] ?? [])));
        $p['scan_size'] = (array)($p['scan_size'] ?? ['php' => 5000000]);
        $p['max_flagged'] = (int)($p['max_flagged'] ?? 5);
        return $p;
    }

    /**
     * @throws JsonException
     */
    private function ensureVersion(array $policy): array
    {
        if (!isset($policy['version'])) {
            $policy['version'] = substr(hash('sha256',
                json_encode($policy, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            ), 0, 12);
        }
        return $policy;
    }

    private function fallback(): array
    {
        return [
            'directory' => 'Plugins',
            'loader' => 'default',
            'stack_depth' => 1,
            'tokens' => ['file_get_contents', 'fopen', 'fwrite', 'fread', 'rename', 'copy', 'scandir', 'glob'],
            'ignore' => [],
            'whitelist' => [],
            'blocklist' => ['DB' => ['transactions', 'rollback', 'commit'], 'File' => ['exists'], 'Storage' => []],
            'dangerous_functions' => ['eval', 'exec', 'shell_exec', 'system', 'passthru', 'unlink'],
            'scan_size' => ['php' => 5000000],
            'max_flagged' => 5,
            'security' => ['must_kyc' => false],
            'publishing' => [
                'max_token_lifetime_days' => 30,
                'allow_public_plugins' => false,
                'require_plugin_review' => true,
            ],
            'admin' => ['allow_admin_override' => true],
        ];
    }

    /**
     * Metadata for caching: strong ETag + last-modified (when available).
     * @return array{etag:string;last_modified:?string,version:string,hash:string,source:string}
     * @throws FileNotFoundException
     * @throws JsonException
     */
    public function meta(): array
    {
        $snap = $this->snapshot();
        $hash = $this->hash();               // sha256 over normalized policy JSON
        $etag = '"forti-' . $hash . '"';     // strong ETag

        $src = $this->detectSource();       // ['type'=>..., 'path'=>..., 'mtime'=>?int]
        $lm = $src['mtime'] ? gmdate('D, d M Y H:i:s', $src['mtime']) . ' GMT' : null;

        return [
            'etag' => $etag,
            'last_modified' => $lm,
            'version' => (string)Arr::get($snap, 'version', '1'),
            'hash' => $hash,
            'source' => $src['type'],
        ];
    }

    // -------------------- internals --------------------

    private function detectSource(): array
    {
        // 1) Inline config
        if (is_array(config('fortiplugin.policy'))) {
            return ['type' => 'config', 'path' => null, 'mtime' => null];
        }

        // 2) Legacy PHP file
        $phpPath = (string)config('fortiplugin.policy_php_path', '');
        if ($phpPath !== '' && $this->fs->exists($phpPath)) {
            return ['type' => 'php', 'path' => $phpPath, 'mtime' => @filemtime($phpPath) ?: null];
        }

        // 3) JSON file
        $jsonPath = (string)config('fortiplugin.policy_path', '');
        if ($jsonPath !== '' && $this->fs->exists($jsonPath)) {
            return ['type' => 'json', 'path' => $jsonPath, 'mtime' => @filemtime($jsonPath) ?: null];
        }

        // 4) Fallback
        return ['type' => 'fallback', 'path' => null, 'mtime' => null];
    }
}

