<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Contracts;

use RuntimeException;

/**
 * Minimal filesystem facade with atomic guarantees and basic introspection.
 *
 * Implementations MUST:
 *  - perform safe, race-aware writes (writeAtomic),
 *  - respect directory creation semantics (ensureDirectory),
 *  - avoid following symlinks during tree copies where possible (copyTree),
 *  - throw \RuntimeException (or a subtype) on failures.
 */
interface Filesystem
{
    /**
     * Whether a path exists (file or directory).
     *
     * @param string $path
     * @return bool
     */
    public function exists(string $path): bool;

    /**
     * Whether the path is a regular file.
     *
     * @param string $path
     * @return bool
     */
    public function isFile(string $path): bool;

    /**
     * Whether the path is a directory.
     *
     * @param string $path
     * @return bool
     */
    public function isDirectory(string $path): bool;

    /**
     * Ensure a directory exists (create recursively if needed).
     *
     * @param string $path Absolute or project-root-relative path.
     * @param int    $mode Permissions (POSIX environments).
     * @return void
     *
     * @throws RuntimeException On failure to create or if a non-directory exists at $path.
     */
    public function ensureDirectory(string $path, int $mode = 0755): void;

    /**
     * Read a file as raw bytes (no decoding).
     *
     * @param string $path
     * @return string
     *
     * @throws RuntimeException If not readable or not a file.
     */
    public function readFile(string $path): string;

    /**
     * Read and decode a JSON file into an associative array.
     *
     * @param string $path
     * @return array
     *
     * @throws RuntimeException If missing, unreadable, or invalid JSON.
     */
    public function readJson(string $path): array;

    /**
     * Atomically write file contents.
     *
     * MUST write to a temporary file in the same directory and rename over the destination.
     *
     * @param string $path
     * @param string $contents
     * @return void
     *
     * @throws RuntimeException On write or rename failure.
     */
    public function writeAtomic(string $path, string $contents): void;

    /**
     * Recursively copy a directory tree.
     *
     * Implementations should avoid copying dangerous entries (e.g., symlinks) and honor an optional filter.
     *
     * @param string        $from   Source directory
     * @param string        $to     Destination directory (will be created if missing)
     * @param callable|null $filter Optional filter with signature fn(string $relativePath): bool
     * @return void
     *
     * @throws RuntimeException On IO errors or invalid arguments.
     */
    public function copyTree(string $from, string $to, ?callable $filter = null): void;

    /**
     * List files under a path (non-recursive or recursive per implementation).
     *
     * @param string        $path
     * @param callable|null $filter Optional filter with signature fn(string $absolutePath): bool
     * @return array<int,string> List of paths
     */
    public function listFiles(string $path, ?callable $filter = null): array;

    /**
     * Rename/move a file or directory.
     *
     * @param string $from
     * @param string $to
     * @return void
     *
     * @throws RuntimeException On failure.
     */
    public function rename(string $from, string $to): void;

    /**
     * Delete a file or directory (recursive for directories).
     *
     * @param string $path
     * @return void
     *
     * @throws RuntimeException On failure.
     */
    public function delete(string $path): void;

    /**
     * File size in bytes, if applicable.
     *
     * @param string $path
     * @return int|null Null if not a file or not determinable.
     */
    public function fileSize(string $path): ?int;
}