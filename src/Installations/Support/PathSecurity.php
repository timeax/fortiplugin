<?php

namespace Timeax\FortiPlugin\Installations\Support;

class PathSecurity
{
    // Phase 0 stubs
    public function validateNoTraversal(string $path): bool { return true; }
    public function validateNoSymlink(string $path): bool { return true; }
}
