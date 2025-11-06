<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Support;

use JsonException;
use RuntimeException;
use Timeax\FortiPlugin\Installations\Contracts\Filesystem;

/**
 * AtomicFilesystem
 *
 * Lightweight helper that layers **atomic JSON operations** on top of a concrete
 * {@see Filesystem} implementation. It does NOT implement the Filesystem contract,
 * so there is no binding/circularity concern. Use this for installer logs and
 * other structured files that must be written atomically.
 *
 * Typical usage:
 *   $afs = new AtomicFilesystem($fs); // $fs is your Contracts\Filesystem
 *   $afs->ensureParentDirectory($pathToJson);
 *   $afs->writeJsonAtomic($pathToJson, $data, true);
 *   $afs->appendJsonArrayAtomic($pathToArrayJson, $item);
 */
final readonly class AtomicFilesystem
{
    public function __construct(private Filesystem $fs) {}

    /**
     * Access to the underlying low-level filesystem.
     * Useful when you need plain readJson(), exists(), etc.
     */
    public function fs(): Filesystem
    {
        return $this->fs;
    }

    /**
     * Ensure the parent directory of a path exists (mkdir -p semantics).
     * Uses native PHP so we don't require extra methods on the Filesystem contract.
     *
     * @throws RuntimeException if the directory cannot be created
     */
    public function ensureParentDirectory(string $path, int $mode = 0755): void
    {
        $dir = dirname($path);
        if (is_dir($dir)) {
            return;
        }
        if (!@mkdir($dir, $mode, true) && !is_dir($dir)) {
            throw new RuntimeException("Cannot create directory: $dir");
        }
    }

    /**
     * Atomically write JSON to a file (UTF-8, no BOM).
     *
     * @param string $path   Absolute or project-relative path
     * @param array  $data   Data to encode
     * @param bool   $pretty Pretty-print JSON (for human-readable logs)
     *
     * @throws JsonException   If encoding fails
     * @throws RuntimeException If the write operation fails
     */
    public function writeJsonAtomic(string $path, array $data, bool $pretty = false): void
    {
        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        if ($pretty) {
            $flags |= JSON_PRETTY_PRINT;
        }

        // Deterministic encode with exceptions so callers can catch details
        $json = json_encode($data, JSON_THROW_ON_ERROR | $flags);
        if ($json === false) {
            // Unreachable with JSON_THROW_ON_ERROR but kept for completeness
            throw new RuntimeException('Failed to encode JSON: ' . json_last_error_msg());
        }

        $this->fs->writeAtomic($path, $json);
    }

    /**
     * Atomically append an item to a JSON array file.
     * If the target file doesn't exist, it is initialized as [] before append.
     *
     * @param string $path Target JSON file that holds a top-level array
     * @param array  $item Item to append
     *
     * @throws JsonException   If encoding/decoding fails
     * @throws RuntimeException If the write operation fails
     */
    public function appendJsonArrayAtomic(string $path, array $item): void
    {
        $arr = [];
        if ($this->fs->exists($path)) {
            $current = $this->fs->readJson($path);
            $arr = is_array($current) ? $current : [];
        }
        $arr[] = $item;

        $this->writeJsonAtomic($path, $arr, true);
    }
}