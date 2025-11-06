<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Infra;

use FilesystemIterator;
use JsonException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Timeax\FortiPlugin\Installations\Contracts\Filesystem;

/**
 * LocalFilesystem
 *
 * Concrete Filesystem using native PHP I/O with **atomic writes**.
 * - writeAtomic(): write to temp file in the same directory, then rename.
 * - copyTree(): recursive copy, skips symlinks, supports relative-path filter.
 * - listFiles(): recursive list of **files only** (not directories).
 */
final class LocalFilesystem implements Filesystem
{
    public function exists(string $path): bool
    {
        return file_exists($path);
    }

    public function isFile(string $path): bool
    {
        return is_file($path);
    }

    public function isDirectory(string $path): bool
    {
        return is_dir($path);
    }

    public function ensureDirectory(string $path, int $mode = 0755): void
    {
        if ($this->isDirectory($path)) {
            return;
        }
        if ($this->exists($path) && !$this->isDirectory($path)) {
            throw new RuntimeException("Path exists and is not a directory: $path");
        }
        if (!mkdir($path, $mode, true) && !is_dir($path) && !$this->isDirectory($path)) {
            throw new RuntimeException("Unable to create directory: $path");
        }
    }

    public function readFile(string $path): string
    {
        if (!$this->isFile($path)) {
            throw new RuntimeException("File not found: $path");
        }
        $raw = @file_get_contents($path);
        if ($raw === false) {
            throw new RuntimeException("Unable to read file: $path");
        }
        return $raw;
    }

    /**
     * @throws JsonException
     */
    public function readJson(string $path): array
    {
        $raw = $this->readFile($path);
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new RuntimeException("Invalid JSON in $path");
        }
        return $data;
    }

    public function writeAtomic(string $path, string $contents): void
    {
        $dir = dirname($path);
        $this->ensureDirectory($dir);

        $tmp = @tempnam($dir, 'fs-');
        if ($tmp === false) {
            throw new RuntimeException("Cannot create temp file in $dir");
        }

        $fh = @fopen($tmp, 'wb');
        if ($fh === false) {
            @unlink($tmp);
            throw new RuntimeException("Cannot open temp file: $tmp");
        }
        $ok = @fwrite($fh, $contents);
        if ($ok === false) {
            @fclose($fh);
            @unlink($tmp);
            throw new RuntimeException("Failed writing temp file: $tmp");
        }
        @fflush($fh);
        @fclose($fh);

        // On Windows, rename fails if target exists—unlink first.
        if ($this->isFile($path) && !@unlink($path)) {
            @unlink($tmp);
            throw new RuntimeException("Cannot replace target file: $path");
        }
        if (!@rename($tmp, $path)) {
            @unlink($tmp);
            throw new RuntimeException("Atomic rename failed: $tmp → $path");
        }
        @chmod($path, 0644);
    }

    public function copyTree(string $from, string $to, ?callable $filter = null): void
    {
        if (!$this->isDirectory($from)) {
            throw new RuntimeException("Source directory not found: $from");
        }
        $this->ensureDirectory($to);

        $srcRoot = rtrim(str_replace('\\', '/', realpath($from) ?: $from), '/').'/';
        $dstRoot = rtrim($to, "/\\").DIRECTORY_SEPARATOR;

        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($from, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($it as $info) {
            /** @var SplFileInfo $info */
            $abs = str_replace('\\', '/', $info->getPathname());
            $rel = ltrim(substr($abs, strlen($srcRoot)), '/'); // relative (forward slashes)

            if ($filter !== null && $filter($rel) === false) {
                continue;
            }
            if ($info->isLink()) {
                // Skip symlinks for safety
                continue;
            }

            $dst = $dstRoot . str_replace('/', DIRECTORY_SEPARATOR, $rel);

            if ($info->isDir()) {
                $this->ensureDirectory($dst);
                continue;
            }

            $this->ensureDirectory(dirname($dst));
            if (!@copy($info->getPathname(), $dst)) {
                throw new RuntimeException("Copy failed: {$info->getPathname()} → $dst");
            }
            @chmod($dst, 0644);
        }
    }

    public function listFiles(string $path, ?callable $filter = null): array
    {
        if (!$this->exists($path)) {
            return [];
        }

        $out = [];
        if ($this->isFile($path)) {
            if ($filter === null || $filter($path) !== false) {
                $out[] = $path;
            }
            return $out;
        }

        if (!$this->isDirectory($path)) {
            return $out;
        }

        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($it as $info) {
            /** @var SplFileInfo $info */
            if ($info->isLink() || !$info->isFile()) {
                continue;
            }
            $abs = $info->getPathname();
            if ($filter === null || $filter($abs) !== false) {
                $out[] = $abs;
            }
        }
        return $out;
    }

    public function rename(string $from, string $to): void
    {
        $this->ensureDirectory(dirname($to));

        if (@rename($from, $to)) {
            return;
        }

        // Cross-device fallback
        if ($this->isDirectory($from)) {
            $this->copyTree($from, $to);
            $this->delete($from);
            return;
        }

        if ($this->isFile($from)) {
            if (!@copy($from, $to)) {
                throw new RuntimeException("Copy fallback failed: $from → $to");
            }
            @unlink($from);
            return;
        }

        throw new RuntimeException("Nothing to rename: $from");
    }

    public function delete(string $path): void
    {
        if ($this->isDirectory($path) && !is_link($path)) {
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($it as $info) {
                $p = $info->getPathname();
                if ($info->isDir() && !is_link($p)) {
                    @rmdir($p);
                } else {
                    @unlink($p);
                }
            }
            if (!@rmdir($path)) {
                throw new RuntimeException("Unable to remove directory: $path");
            }
            return;
        }

        if ($this->isFile($path) || is_link($path)) {
            if (!@unlink($path)) {
                throw new RuntimeException("Unable to delete file: $path");
            }
        }

        // No-op if already gone
    }

    public function fileSize(string $path): ?int
    {
        if (!$this->isFile($path)) {
            return null;
        }
        $s = @filesize($path);
        return $s === false ? null : $s;
    }
}