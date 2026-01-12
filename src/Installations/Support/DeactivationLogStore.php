<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Support;

use JsonException;
use RuntimeException;
use Timeax\FortiPlugin\Installations\Contracts\Filesystem;
use Timeax\FortiPlugin\Installations\Lifecycle\Deactivation\DeactivationResult;

/**
 * Concrete deactivation.json store with atomic writes.
 *
 * File shape:
 * {
 *   "meta": {...},
 *   "logs": {
 *     "deactivation_emits": [ ... ]
 *   },
 *   "result": {...}|null
 * }
 */
final class DeactivationLogStore
{
    private AtomicFilesystem $atomFs;
    private Filesystem $fs;
    private ?string $deactivationJsonPath = null;
    /** @var array{meta?:array,logs?:array,result?:array} */
    private array $doc = [];

    public function __construct(AtomicFilesystem $atomFs)
    {
        $this->atomFs = $atomFs;
        $this->fs = $atomFs->fs();
    }

    private function assertReady(): void
    {
        if ($this->deactivationJsonPath === null || $this->deactivationJsonPath === '') {
            throw new RuntimeException('DeactivationLogStore has no path. Call init() or openOrInit() first.');
        }
    }

    /**
     * @throws JsonException
     */
    public function init(array $meta, string $deactivationJsonPath): string
    {
        $this->deactivationJsonPath = $deactivationJsonPath;
        $dir = dirname($this->deactivationJsonPath);
        $this->fs->ensureDirectory($dir);

        $this->doc = [
            'meta' => $meta,
            'logs' => [
                'deactivation_emits' => [],
            ],
            'result' => null,
        ];
        $this->persist();
        return $this->deactivationJsonPath;
    }

    /**
     * @throws JsonException
     */
    public function openOrInit(array $meta, string $deactivationJsonPath): string
    {
        if ($this->deactivationJsonPath !== null) {
            if ($this->deactivationJsonPath !== $deactivationJsonPath) {
                throw new RuntimeException('DeactivationLogStore already initialized with a different path.');
            }
            return $this->deactivationJsonPath;
        }

        $this->deactivationJsonPath = $deactivationJsonPath;

        if ($this->fs->exists($this->deactivationJsonPath)) {
            $this->doc = []; // force read() to load fresh
            return $this->deactivationJsonPath;
        }

        return $this->init($meta, $this->deactivationJsonPath);
    }

    /** @param array $payload
     * @throws JsonException
     */
    public function appendDeactivationEmit(array $payload): void
    {
        $doc = $this->read();
        $doc['logs']['deactivation_emits'][] = $payload;
        $this->doc = $doc;
        $this->persist();
    }

    /**
     * @throws JsonException
     */
    public function writeResult(DeactivationResult $result): void
    {
        $doc = $this->read();
        $doc['result'] = [
            'status' => $result->status,
            'data' => $result->data
        ];
        $this->doc = $doc;
        $this->persist();
    }

    public function path(): string
    {
        $this->assertReady();
        return $this->deactivationJsonPath;
    }

    /** @return array{meta?:array,logs?:array,result?:array} */
    public function read(): array
    {
        $this->assertReady();

        if ($this->doc !== []) {
            return $this->doc;
        }
        if (!$this->fs->exists($this->deactivationJsonPath)) {
            throw new RuntimeException("deactivation.json not initialized at $this->deactivationJsonPath");
        }
        $this->doc = $this->fs->readJson($this->deactivationJsonPath);
        $this->doc['logs'] = $this->doc['logs'] ?? ['deactivation_emits' => []];
        return $this->doc;
    }

    /**
     * @throws JsonException
     */
    private function persist(): void
    {
        $this->atomFs->writeJsonAtomic($this->deactivationJsonPath, $this->doc, true);
    }
}