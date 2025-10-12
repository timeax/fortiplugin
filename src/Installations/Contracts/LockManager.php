<?php

namespace Timeax\FortiPlugin\Installations\Contracts;

interface LockManager
{
    public function acquire(string $slug): bool;

    public function release(string $slug): void;
}
