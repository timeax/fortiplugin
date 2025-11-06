<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Infra;

use Illuminate\Contracts\Container\BindingResolutionException;
use JsonException;
use RuntimeException;
use Timeax\FortiPlugin\Installations\Contracts\Filesystem;
use Timeax\FortiPlugin\Installations\Support\PathSecurity;

/**
 * InMemoryFilesystem
 *
 * Simple in-memory implementation for unit tests. Not persistent.
 * Paths are normalized to forward slashes internally.
 */
final class InMemoryFilesystem implements Filesystem
{
    /** @var array<string,string> */
    private array $files = [];     // path => contents
    /** @var array<string,bool> */
    private array $dirs  = ['/'=>true];

    /**
     * @throws BindingResolutionException
     */
    private function norm(string $p): string
    {
        return app()->make(PathSecurity::class)->normalize($p);
    }

    /**
     * @throws BindingResolutionException
     */
    public function exists(string $path): bool
    {
        $n = $this->norm($path);
        return isset($this->files[$n]) || isset($this->dirs[$n]);
    }

    /**
     * @throws BindingResolutionException
     */
    public function isFile(string $path): bool
    {
        return isset($this->files[$this->norm($path)]);
    }

    /**
     * @throws BindingResolutionException
     */
    public function isDirectory(string $path): bool
    {
        return isset($this->dirs[$this->norm($path)]);
    }

    /**
     * @throws BindingResolutionException
     */
    public function ensureDirectory(string $path, int $mode = 0755): void
    {
        $n = $this->norm($path);
        // simulate file exists at path
        if (isset($this->files[$n])) {
            throw new RuntimeException("Path exists and is not a directory: $path");
        }
        // mkdir -p
        $parts = [];
        foreach (explode('/', trim($n, '/')) as $seg) {
            if ($seg === '') continue;
            $parts[] = $seg;
            $this->dirs['/'.implode('/', $parts)] = true;
        }
        $this->dirs[$n] = true;
    }

    /**
     * @throws BindingResolutionException
     */
    public function readFile(string $path): string
    {
        $n = $this->norm($path);
        if (!isset($this->files[$n])) {
            throw new RuntimeException("File not found: $path");
        }
        return $this->files[$n];
    }

    /**
     * @throws JsonException|BindingResolutionException
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

    /**
     * @throws BindingResolutionException
     */
    public function writeAtomic(string $path, string $contents): void
    {
        $n = $this->norm($path);
        $this->ensureDirectory(dirname($n));
        $this->files[$n] = $contents;
    }

    /**
     * @throws BindingResolutionException
     */
    public function copyTree(string $from, string $to, ?callable $filter = null): void
    {
        $src = rtrim($this->norm($from), '/').'/';
        $dst = rtrim($this->norm($to), '/');

        if (!$this->isDirectory($from)) {
            throw new RuntimeException("Source directory not found: $from");
        }
        $this->ensureDirectory($dst);

        foreach ($this->files as $p => $c) {
            if (str_starts_with($p, $src)) {
                $rel = substr($p, strlen($src)); // relative (forward slashes)
                if ($filter !== null && $filter($rel) === false) {
                    continue;
                }
                $target = $dst . '/' . $rel;
                $this->writeAtomic($target, $c);
            }
        }
    }

    /**
     * @throws BindingResolutionException
     */
    public function listFiles(string $path, ?callable $filter = null): array
    {
        $root = rtrim($this->norm($path), '/');
        if ($this->isFile($root)) {
            return ($filter === null || $filter($root) !== false) ? [$root] : [];
        }

        $out = [];
        $prefix = $root === '/' ? '/' : $root.'/';
        foreach ($this->files as $p => $_) {
            if ($root === '/' || str_starts_with($p, $prefix)) {
                if ($filter === null || $filter($p) !== false) {
                    $out[] = $p;
                }
            }
        }
        return $out;
    }

    /**
     * @throws BindingResolutionException
     */
    public function rename(string $from, string $to): void
    {
        $src = $this->norm($from);
        $dst = $this->norm($to);
        $this->ensureDirectory(dirname($dst));

        // Move a file
        if (isset($this->files[$src])) {
            $this->files[$dst] = $this->files[$src];
            unset($this->files[$src]);
            return;
        }

        // Move a directory (by prefix)
        $srcPrefix = rtrim($src, '/').'/';
        $moved = false;
        foreach (array_keys($this->files) as $p) {
            if (str_starts_with($p, $srcPrefix)) {
                $rel = substr($p, strlen($srcPrefix));
                $this->files[rtrim($dst, '/').'/'.$rel] = $this->files[$p];
                unset($this->files[$p]);
                $moved = true;
            }
        }
        if ($moved) {
            unset($this->dirs[$src]);
            $this->dirs[$dst] = true;
            return;
        }

        throw new RuntimeException("Nothing to rename: $from");
    }

    /**
     * @throws BindingResolutionException
     */
    public function delete(string $path): void
    {
        $n = $this->norm($path);

        if (isset($this->files[$n])) {
            unset($this->files[$n]);
            return;
        }

        if (isset($this->dirs[$n])) {
            $prefix = rtrim($n, '/').'/';
            foreach (array_keys($this->files) as $p) {
                if (str_starts_with($p, $prefix)) {
                    unset($this->files[$p]);
                }
            }
            foreach (array_keys($this->dirs) as $d) {
                if ($d === $n || str_starts_with($d, $prefix)) {
                    unset($this->dirs[$d]);
                }
            }
        }

        // no-op if already gone
    }

    /**
     * @throws BindingResolutionException
     */
    public function fileSize(string $path): ?int
    {
        $n = $this->norm($path);
        return isset($this->files[$n]) ? strlen($this->files[$n]) : null;
    }
}