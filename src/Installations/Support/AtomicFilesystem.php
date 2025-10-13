<?php

namespace Timeax\FortiPlugin\Installations\Support;

use RuntimeException;

class AtomicFilesystem
{
    public function ensureDir(string $dir): void
    {
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0777, true) && !is_dir($dir)) {
                throw new RuntimeException('Failed to create directory: ' . $dir);
            }
        }
    }

    /**
     * Copy a directory tree. Optional $filter(filePathRelativeToRoot): bool can skip items.
     * PathSecurity checks are consulted if provided.
     */
    public function copyTree(string $from, string $to, ?callable $filter = null, ?PathSecurity $sec = null): void
    {
        $from = rtrim($from, "\\/ ");
        $to = rtrim($to, "\\/ ");
        if (!is_dir($from)) {
            throw new RuntimeException('Source directory does not exist: ' . $from);
        }
        $this->ensureDir($to);
        $rootLen = strlen($from) + 1;
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($from, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($it as $path => $info) {
            // Skip symlinks always
            if (is_link($path)) continue;
            $rel = substr($path, $rootLen);
            if ($filter && $filter($rel) === false) continue;
            $sec && $sec->validateNoTraversal($rel);
            $sec && $sec->validateNoSymlink($path);
            $dest = $to . DIRECTORY_SEPARATOR . $rel;
            if ($info->isDir()) {
                $this->ensureDir($dest);
            } else {
                $dir = dirname($dest);
                $this->ensureDir($dir);
                if (!@copy($path, $dest)) {
                    throw new RuntimeException('Failed to copy file: ' . $path);
                }
            }
        }
    }

    public function atomicWrite(string $path, string $contents): void
    {
        $dir = dirname($path);
        $this->ensureDir($dir);
        $tmp = $path . '.tmp';
        if (@file_put_contents($tmp, $contents) === false) {
            throw new RuntimeException('Failed to write temp file: ' . $tmp);
        }
        $this->safeRename($tmp, $path);
    }

    public function safeRename(string $from, string $to): void
    {
        $dir = dirname($to);
        $this->ensureDir($dir);
        if (!@rename($from, $to)) {
            throw new RuntimeException('Failed to rename ' . $from . ' to ' . $to);
        }
    }

    public function removeDir(string $dir): void
    {
        if (!is_dir($dir)) return;
        $items = scandir($dir);
        if ($items === false) return;
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path) && !is_link($path)) {
                $this->removeDir($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
