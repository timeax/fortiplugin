<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Support;

use JsonException;
use RuntimeException;
use Timeax\FortiPlugin\Installations\Contracts\Filesystem;
use Timeax\FortiPlugin\Installations\Lifecycle\Activation\ActivationResult;

/**
 * Concrete activation.json store with atomic writes.
 *
 * File shape:
 * {
 *   "meta": {...},
 *   "logs": {
 *     "activation_emits": [ ... ]
 *   },
 *   "result": {...}|null
 * }
 */
final class ActivationLogStore
{
    private AtomicFilesystem $atomFs;
    private Filesystem $fs;
    private ?string $activationJsonPath = null;
    /** @var array{meta?:array,logs?:array,result?:array} */
    private array $doc = [];

    public function __construct(AtomicFilesystem $atomFs)
    {
        $this->atomFs = $atomFs;
        $this->fs = $atomFs->fs();
    }

    private function assertReady(): void
    {
        if ($this->activationJsonPath === null || $this->activationJsonPath === '') {
            throw new RuntimeException('ActivationLogStore has no path. Call init() or openOrInit() first.');
        }
    }

    /**
     * @throws JsonException
     */
    public function init(array $meta, string $activationJsonPath): string
    {
        $this->activationJsonPath = $activationJsonPath;
        $dir = dirname($this->activationJsonPath);
        $this->fs->ensureDirectory($dir);

        $this->doc = [
            'meta' => $meta,
            'logs' => [
                'activation_emits' => [],
            ],
            'result' => null,
        ];
        $this->persist();
        return $this->activationJsonPath;
    }

    /**
     * @throws JsonException
     */
    public function openOrInit(array $meta, string $activationJsonPath): string
    {
        if ($this->activationJsonPath !== null) {
            // already bound to a run; don't allow switching silently
            if ($this->activationJsonPath !== $activationJsonPath) {
                throw new RuntimeException('ActivationLogStore already initialized with a different path.');
            }
            return $this->activationJsonPath;
        }

        $this->activationJsonPath = $activationJsonPath;

        // If file exists, just attach (do NOT overwrite)
        if ($this->fs->exists($this->activationJsonPath)) {
            $this->doc = []; // force read() to load fresh when called
            return $this->activationJsonPath;
        }

        // Otherwise create it
        return $this->init($meta, $this->activationJsonPath);
    }

    /** @param array $payload
     * @throws JsonException
     */
    public function appendActivationEmit(array $payload): void
    {
        $doc = $this->read();
        $doc['logs']['activation_emits'][] = $payload; // verbatim
        $this->doc = $doc;
        $this->persist();
    }

    /**
     * @throws JsonException
     */
    public function writeResult(ActivationResult $result): void
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
        return $this->activationJsonPath;
    }

    /** @return array{meta?:array,logs?:array,result?:array} */
    public function read(): array
    {
        $this->assertReady();

        if ($this->doc !== []) {
            return $this->doc;
        }
        if (!$this->fs->exists($this->activationJsonPath)) {
            throw new RuntimeException("activation.json not initialized at $this->activationJsonPath");
        }
        $this->doc = $this->fs->readJson($this->activationJsonPath);
        // Guards for missing keys if the file was created by older versions
        $this->doc['logs'] = $this->doc['logs'] ?? ['activation_emits' => []];
        return $this->doc;
    }

    /**
     * Persist an arbitrary structured section under a top-level key
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
        $this->atomFs->writeJsonAtomic($this->activationJsonPath, $this->doc, true);
    }
}