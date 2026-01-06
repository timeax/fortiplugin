<?php

namespace Timeax\FortiPlugin\Support;

use JsonSerializable;
use Throwable;

final readonly class LoadedExportInfo implements JsonSerializable
{
    public function __construct(
        public string $type,         // "class"
        public string $file,         // relative (as in config)
        public string $realpath,     // absolute
        public string $classString,  // FQCN
        public string $className,    // short name
        public string $namespace,
        public string $sha1,
        public int    $mtime,
        public string $resolvedAt,
    )
    {
    }

    /** @param array<string,mixed> $a */
    public static function fromArray(array $a): self
    {
        return new self(
            (string)($a['type'] ?? 'class'),
            (string)($a['file'] ?? ''),
            (string)($a['realpath'] ?? ''),
            (string)($a['classString'] ?? ''),
            (string)($a['className'] ?? ''),
            (string)($a['namespace'] ?? ''),
            (string)($a['sha1'] ?? ''),
            (int)($a['mtime'] ?? 0),
            (string)($a['resolved_at'] ?? $a['resolvedAt'] ?? ''),
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'type' => $this->type,
            'file' => $this->file,
            'realpath' => $this->realpath,
            'classString' => $this->classString,
            'className' => $this->className,
            'namespace' => $this->namespace,
            'sha1' => $this->sha1,
            'mtime' => $this->mtime,
            'resolved_at' => $this->resolvedAt,
        ];
    }

    /** @param array<int,mixed> $args
     * @return class-string|object|null FQCN or instantiated object, or null on failure
     */
    public function resolve(bool $instantiate = false, array $args = []): mixed
    {
        if ($this->type !== 'class') {
            return null;
        }

        $fqcn = trim($this->classString, '\\');
        if ($fqcn === '') {
            return null;
        }

        // Prefer autoload / already-loaded
        if (!class_exists($fqcn) && $this->realpath !== '' && is_file($this->realpath)) {
            // Only then load the file explicitly (safe, because install persisted realpath)
            require_once $this->realpath;
        }

        if (!class_exists($fqcn)) {
            return null;
        }

        if (!$instantiate) {
            return $fqcn;
        }

        try {
            return new $fqcn(...$args);
        } catch (Throwable) {
            return null;
        }
    }
}