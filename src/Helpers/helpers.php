<?php

use Timeax\FortiPlugin\Enums\ProcessType;
use Timeax\FortiPlugin\Models\PluginProcess;
use Timeax\FortiPlugin\Support\FortiAuth;

if (!function_exists('forti_author')) {
    /**
     * Get the current authenticated author.
     *
     * @return \Timeax\FortiPlugin\Models\Author|null
     */
    function forti_author()
    {
        return FortiAuth::author();
    }
}

if (!function_exists('stripComments')) {
    /**
     * Removes both single-line (//) and multi-line (/* *​/) comments from a JSON string.
     *
     * This function is useful for preprocessing JSON files that may contain comments,
     * which are not officially supported in the JSON specification. It uses regular
     * expressions to strip out both // line comments and /* block comments *​/ from the input string.
     *
     * @param string $json The JSON string potentially containing comments.
     * @return string The JSON string with all comments removed.
     */
    function stripComments(string $json): string
    {
        // Remove // line comments
        $json = preg_replace('/\/\/[^\n\r]*/', '', $json);
        // Remove /* block comments */
        return preg_replace('/\/\*.*?\*\//s', '', $json);
    }
}

if (!function_exists('ensureFileExistsAtomic')) {
    /**
     * Ensure a file exists without race-condition warnings.
     * Creates the file atomically with 'x' mode; silently succeeds if it already exists.
     */
    function ensureFileExistsAtomic(string $path): void
    {
        if (is_file($path)) {
            return;
        }

        // Try atomic create (fails harmlessly if another process won the race)
        $h = @fopen($path, 'xb');
        if ($h !== false) {
            fclose($h);
        } else {
            // If 'x' failed for non-existence reasons, fall back to touch; ignore warnings
            @touch($path);
        }

        // Best-effort: make sure it’s writable
        if (!is_writable($path)) {
            @chmod($path, 0666 & ~umask());
        }
    }
}

if (!function_exists('plugin_path')) {
    /**
     * Get the path to the plugins directory.
     *
     * @param string $path Optional subpath to append to the plugins directory.
     * @return string The full path to the plugins directory or the specified subpath.
     */
    function plugin_path(string $path = ''): string
    {
        $basePath = base_path(config('fortiplugin.install_directory'));
        return $path ? $basePath . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR) : $basePath;
    }
}

if (!function_exists('forti_installation_log_path')) {
    function forti_installation_log_path(int $zipId, string $runId): string
    {
        // staging dir format (based on your sample): "{zip_id}-{run_id}"
        $runDir = storage_path("app/fortiplugin/staging/{$zipId}-{$runId}");

        // The installer persists here (per your meta.paths.logs)
        return $runDir . DIRECTORY_SEPARATOR . '.internal' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'installation.json';
    }
}

if (!function_exists('read_forti_process_log')) {
    /**
     * Read a single PluginProcess + its installation log (decoded array or null).
     *
     * @return array{process: array<string,mixed>, log: array<string,mixed>|null, log_path: string|null, log_mtime: int|null}
     * @throws JsonException
     */
    function read_forti_process_log(int $processId): array
    {
        /** @var PluginProcess|null $process */
        $process = PluginProcess::query()->where('type', ProcessType::installer)->find($processId);

        if (!$process) {
            return [
                'process' => [],
                'log' => null,
                'log_path' => null,
                'log_mtime' => null,
            ];
        }

        $zipId = $process->source_id;
        $runId = $process->run_id;

        $path = forti_installation_log_path($zipId, $runId);

        $log = null;
        $mtime = null;

        if (is_file($path)) {
            $mtime = @filemtime($path) ?: null;

            $raw = file_get_contents($path);
            if (is_string($raw) && $raw !== '') {
                $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded) && json_last_error() === JSON_ERROR_NONE) {
                    $log = $decoded;
                } else {
                    // If it’s corrupted mid-write, keep null; UI will re-fetch on next emit.
                    $log = null;
                }
            }
        }

        return [
            'process' => [
                'id' => $process->id,
                'zip_id' => $process->source_id,
                'run_id' => $process->run_id,
                'status' => $process->status,
                'created_at' => $process->created_at?->toISOString(),
                'updated_at' => $process->updated_at?->toISOString(),
            ],
            'log' => $log,
            'log_path' => is_file($path) ? $path : null,
            'log_mtime' => $mtime,
        ];
    }
}


require_once __DIR__ . '/ui-helpers.php';