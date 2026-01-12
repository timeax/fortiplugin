<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Lifecycle\Writers\Concerns;

use Throwable;
use Timeax\FortiPlugin\Installations\Support\AtomicFilesystem;

trait RegistryWriteHelpers
{
    abstract protected function afs(): AtomicFilesystem;

    /**
     * Read JSON safely. Always returns an array.
     * @return array<mixed>
     */
    protected function readJsonSafe(string $path): array
    {
        $fs = $this->afs()->fs();

        try {
            $json = $fs->exists($path) ? $fs->readJson($path) : [];
        } catch (Throwable) {
            $json = [];
        }

        return is_array($json) ? $json : [];
    }

    /**
     * Stage a JSON mutation with commit/rollback.
     *
     * @param callable(array<mixed>): array<mixed> $mutator
     */
    protected function stageJsonMutation(string $path, callable $mutator, array $meta = []): array
    {
        $prev = $this->readJsonSafe($path);
        $next = $mutator($prev);

        if (!is_array($next)) {
            // guard: mutator must return array
            $next = $prev;
        }

        if ($next === $prev) {
            return [
                'commit' => static function (): void {},
                'rollback' => static function (): void {},
                'meta' => $meta + ['changed' => false, 'reason' => 'no_change', 'registry_path' => $path],
            ];
        }

        return [
            'commit' => function () use ($path, $next): void {
                $this->afs()->writeJsonAtomic($path, $next, true);
            },
            'rollback' => function () use ($path, $prev): void {
                $this->afs()->writeJsonAtomic($path, $prev, true);
            },
            'meta' => $meta + ['changed' => true, 'registry_path' => $path],
        ];
    }

    /**
     * Convenience: remove a key from a JSON registry (no-op if missing).
     */
    protected function stageJsonRemoveKey(string $path, string $key, array $meta = []): array
    {
        return $this->stageJsonMutation(
            $path,
            static function (array $prev) use ($key): array {
                if (array_key_exists($key, $prev)) {
                    unset($prev[$key]);
                }
                return $prev;
            },
            $meta + ['action' => 'removed', 'key' => $key]
        );
    }

    /**
     * Read text file safely (returns null if missing).
     */
    protected function readTextOrNull(string $path): ?string
    {
        $fs = $this->afs()->fs();

        try {
            return $fs->exists($path) ? (string)$fs->read($path) : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Stage a text write with rollback.
     * If previous was null (file missing), rollback deletes the file (best effort).
     */
    protected function stageTextWrite(string $path, string $contents, array $meta = []): array
    {
        $fs = $this->afs()->fs();
        $prev = $this->readTextOrNull($path);

        if (($prev ?? '') === $contents) {
            return [
                'commit' => static function (): void {},
                'rollback' => static function (): void {},
                'meta' => $meta + ['changed' => false, 'reason' => 'no_change', 'path' => $path],
            ];
        }

        return [
            'commit' => function () use ($path, $contents): void {
                $this->afs()->fs()->writeAtomic($path, $contents);
            },
            'rollback' => function () use ($path, $prev, $fs): void {
                if ($prev === null) {
                    if ($fs->exists($path)) {
                        $fs->delete($path);
                    }
                    return;
                }
                $fs->writeAtomic($path, $prev);
            },
            'meta' => $meta + ['changed' => true, 'path' => $path],
        ];
    }

    /**
     * Compose two staged operations into one (commit both / rollback both).
     */
    protected function combineStages(array $a, array $b, array $meta = []): array
    {
        return [
            'commit' => function () use ($a, $b): void {
                ($a['commit'])();
                ($b['commit'])();
            },
            'rollback' => function () use ($a, $b): void {
                // rollback in reverse order
                ($b['rollback'])();
                ($a['rollback'])();
            },
            'meta' => $meta + [
                    'changed' => (bool)($a['meta']['changed'] ?? false) || (bool)($b['meta']['changed'] ?? false),
                    'parts' => [$a['meta'] ?? [], $b['meta'] ?? []],
                ],
        ];
    }
}