<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Support;

use JsonException;
use RuntimeException;
use Timeax\FortiPlugin\Installations\Contracts\Filesystem;
use Timeax\FortiPlugin\Installations\DTO\InstallMeta;
use Timeax\FortiPlugin\Installations\DTO\InstallSummary;
use Timeax\FortiPlugin\Installations\DTO\DecisionResult;

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
 *   "decision": {...}|null
 * }
 */
final class InstallationLogStore
{
    private AtomicFilesystem $atomFs;
    private Filesystem $fs;
    private string $installationJsonPath;
    /** @var array{meta?:array,logs?:array,summary?:array,decision?:array} */
    private array $doc = [];


    public function __construct(AtomicFilesystem $atomFs, string $installationJsonPath)
    {
        $this->atomFs = $atomFs;
        $this->fs = $atomFs->fs();
        $this->installationJsonPath = $installationJsonPath;
    }

    /**
     * @throws JsonException
     */
    public function init(InstallMeta $meta): string
    {
        $dir = dirname($this->installationJsonPath);
        $this->fs->ensureDirectory($dir);

        $this->doc = [
            'meta' => $meta->toArray(),
            'logs' => [
                'validation_emits' => [],
                'installer_emits' => [],
            ],
            'summary' => null,
            'decision' => null,
        ];
        $this->persist();
        return $this->installationJsonPath;
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
        $doc['decision'] = $decision->toArray();
        $this->doc = $doc;
        $this->persist();
    }

    public function path(): string
    {
        return $this->installationJsonPath;
    }

    /** @return array{meta?:array,logs?:array,summary?:array,decision?:array} */
    public function read(): array
    {
        if ($this->doc !== []) {
            return $this->doc;
        }
        if (!$this->fs->exists($this->installationJsonPath)) {
            throw new RuntimeException("installation.json not initialized at $this->installationJsonPath");
        }
        $this->doc = $this->fs->readJson($this->installationJsonPath);
        // Guards for missing keys if the file was created by older versions
        $this->doc['logs'] = $this->doc['logs'] ?? ['validation_emits' => [], 'installer_emits' => []];
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
        $this->atomFs->writeJsonAtomic($this->installationJsonPath, $this->doc, true);
    }
}