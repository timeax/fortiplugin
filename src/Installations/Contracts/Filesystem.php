<?php

namespace Timeax\FortiPlugin\Installations\Contracts;

interface Filesystem
{
    public function exists(string $path): bool;
    public function readJson(string $path): array;
    public function writeAtomic(string $path, string $contents): void;
    public function copyTree(string $from, string $to, ?callable $filter = null): void;
    public function rename(string $from, string $to): void;
    public function delete(string $path): void;
}
