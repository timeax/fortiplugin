<?php

namespace Timeax\FortiPlugin\Installations\Support;

class Fingerprint
{
    // Phase 0 stubs
    public function compute(string $zipPath): string { return hash('sha256', $zipPath); }
    public function configHash(array $config): string { return hash('sha256', json_encode($config)); }
}
