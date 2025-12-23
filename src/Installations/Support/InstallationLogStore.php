<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Support;

use JsonException;
use RuntimeException;
use Timeax\FortiPlugin\Installations\Contracts\Filesystem;
use Timeax\FortiPlugin\Installations\DTO\DecisionResult;
use Timeax\FortiPlugin\Installations\DTO\InstallMeta;
use Timeax\FortiPlugin\Installations\DTO\InstallSummary;

/**
 * Concrete installation.json store with atomic writes and verbatim validation logs.
 *
 * File shape:
 * {
 *   "meta": {...},
 *   "logs": {
 *     "validation_emits": [ ... ],
 *     "installer_emits":  [ ... ]
 *   },
 *   "summary": {...}|null,
 *   "decisions": {...}|null
 * }
 */
final class InstallationLogStore
{
    private AtomicFilesystem $atomFs;
    private Filesystem $fs;
    private ?string $installationJsonPath = null;
    /** @var array{meta?:array,logs?:array,summary?:array,decisions?:array} */
    private array $doc = [];


    public function __construct(AtomicFilesystem $atomFs)
    {
        $this->atomFs = $atomFs;
        $this->fs = $atomFs->fs();

    }

    private function assertReady(): void
    {
        if ($this->installationJsonPath === null || $this->installationJsonPath === '') {
            throw new RuntimeException('InstallationLogStore has no path. Call init() or openOrInit() first.');
        }
    }

    /**
     * @throws JsonException
     */
    public function init(InstallMeta $meta, string $installationJsonPath): string
    {


        $this->installationJsonPath = $installationJsonPath;
        $dir = dirname($this->installationJsonPath);
        $this->fs->ensureDirectory($dir);

        $this->doc = [
            'meta' => $meta->toArray(),
            'logs' => [
                'validation_emits' => [],
                'installer_emits' => [],
            ],
            'summary' => null,
            'decisions' => null,
        ];
        $this->persist();
        return $this->installationJsonPath;
    }

    /**
     * @throws JsonException
     */
    public function openOrInit(InstallMeta $meta, string $installationJsonPath): string
    {
        if ($this->installationJsonPath !== null) {
            // already bound to a run; don't allow switching silently
            if ($this->installationJsonPath !== $installationJsonPath) {
                throw new RuntimeException('InstallationLogStore already initialized with a different path.');
            }
            return $this->installationJsonPath;
        }

        $this->installationJsonPath = $installationJsonPath;

        // If file exists, just attach (do NOT overwrite)
        if ($this->fs->exists($this->installationJsonPath)) {
            $this->doc = []; // force read() to load fresh when called
            return $this->installationJsonPath;
        }

        // Otherwise create it
        return $this->init($meta, $this->installationJsonPath);
    }


    /** @param array $payload
     * @throws JsonException
     * @throws JsonException
     */
    public function appendValidationEmit(array $payload): void
    {
        $doc = $this->read();
        $doc['logs']['validation_emits'][] = $payload; // verbatim
        $this->doc = $doc;
        $this->persist();
    }

    /** @param array $payload
     * @throws JsonException
     * @throws JsonException
     */
    public function appendInstallerEmit(array $payload): void
    {
        $doc = $this->read();
        $doc['logs']['installer_emits'][] = $payload; // terse, but verbatim too
        $this->doc = $doc;
        $this->persist();
    }

    /**
     * @throws JsonException
     */
    public function writeSummary(InstallSummary $summary): void
    {
        $doc = $this->read();
        $doc['summary'] = $summary->toArray();
        $this->doc = $doc;
        $this->persist();
    }

    /**
     * @throws JsonException
     */
    public function writeDecision(DecisionResult $decision): void
    {
        $doc = $this->read();

        $doc['decisions'] = $doc['decisions'] ?? [];
        if (!is_array($doc['decisions'])) {
            $doc['decisions'] = [];
        }

        $doc['decisions'][] = $decision->toArray();

        // Stop writing/using the decision (singular) key
        unset($doc['decision']);

        $this->doc = $doc;
        $this->persist();
    }

    public function path(): string
    {
        $this->assertReady();
        return $this->installationJsonPath;
    }

    /** @return array{meta?:array,logs?:array,summary?:array,decisions?:array} */
    public function read(): array
    {
        $this->assertReady();

        if ($this->doc !== []) {
            return $this->doc;
        }
        if (!$this->fs->exists($this->installationJsonPath)) {
            throw new RuntimeException("installation.json not initialized at $this->installationJsonPath");
        }
        $doc = $this->fs->readJson($this->installationJsonPath);
        // Guards for missing keys if the file was created by older versions
        $doc['logs'] = $doc['logs'] ?? ['validation_emits' => [], 'installer_emits' => []];

        $this->doc = $this->derelativizeMeta($doc);

        return $this->doc;
    }

    /**
     * Persist an arbitrary structured section under a top-level key
     * like "vendor_policy", "file_scan", "composer_plan", etc.
     *
     * @throws JsonException
     */
    public function writeSection(string $key, array $block): void
    {
        $this->assertReady();

        $doc = $this->read();
        $doc[$key] = $block;
        $this->doc = $doc;
        $this->persist();
    }

    /**
     * Read a previously written section (or null if absent).
     */
    public function readSection(string $key): ?array
    {
        $doc = $this->read();
        $val = $doc[$key] ?? null;
        return is_array($val) ? $val : null;
    }

    /**
     * @throws JsonException
     */
    private function persist(): void
    {
        $doc = $this->doc;
        if (isset($doc['meta'])) {
            $doc['meta'] = $this->relativizeMeta($doc['meta']);
        }
        $this->atomFs->writeJsonAtomic($this->installationJsonPath, $doc, true);
    }

    private function relativizeMeta(array $meta): array
    {
        if (isset($meta['paths']) && is_array($meta['paths'])) {
            $base = function_exists('base_path') ? base_path() : getcwd();
            foreach ($meta['paths'] as $k => $v) {
                if (is_string($v)) {
                    $meta['paths'][$k] = $this->toRelative($v, $base);
                }
            }
        }
        return $meta;
    }

    private function derelativizeMeta(array $meta): array
    {
        if (isset($meta['paths']) && is_array($meta['paths'])) {
            $base = function_exists('base_path') ? base_path() : getcwd();
            foreach ($meta['paths'] as $k => $v) {
                if (is_string($v)) {
                    $meta['paths'][$k] = $this->toAbsolute($v, $base);
                }
            }
        }
        return $meta;
    }

    private function toRelative(string $path, string $base): string
    {
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        $base = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $base);

        if ($path !== '' && str_starts_with($path, $base)) {
            return ltrim(substr($path, strlen($base)), DIRECTORY_SEPARATOR);
        }
        return $path;
    }

    private function toAbsolute(string $path, string $base): string
    {
        if ($path === '') return '';

        // If it's already absolute, return as is
        // Windows: C:\... or \\...  ; Linux: /...
        if (str_starts_with($path, DIRECTORY_SEPARATOR)
            || str_starts_with($path, '/')
            || preg_match('/^[a-zA-Z]:\\\\/', $path)
        ) {
            return $path;
        }

        return rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $path;
    }

    // InstallationLogStore.php

    public function makeInstallerEmitter(?callable $forward = null, ?callable $tee = null): callable
    {
        return function (array $payload) use ($forward, $tee): void {
            // 1) Always persist
            $this->appendInstallerEmit($payload);

            // 2) Optional CLI tee
            if ($tee) {
                $tee($payload);
            }

            // 3) Optional external forward (UI, etc.)
            if ($forward) {
                $forward($payload);
            }
        };
    }

}