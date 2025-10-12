<?php

namespace Timeax\FortiPlugin\Installations\Contracts;

interface Emitter
{
    /**
     * Unified event payload emitter. Implementations should accept any associative array payload
     * with keys like: title, description, error, stats (filePath,size), meta (optional).
     *
     * @param array $payload
     */
    public function __invoke(array $payload): void;
}
