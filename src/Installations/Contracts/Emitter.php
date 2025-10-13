<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Contracts;

/**
 * Unified event emitter for installer + validator streams.
 *
 * MUST be non-throwing and MUST NOT mutate the given payload.
 * Typical implementations: multiplex to UI, append to installation.json logs, emit metrics.
 *
 * Expected payload structure (associative array):
 *  - title        : string
 *  - description  : string|null
 *  - error        : array|null     // arbitrary detail, if any
 *  - stats        : array{filePath?:string|null,size?:int|null}|null
 *  - meta         : array|null     // section-specific extras (token purpose, ids, etc.)
 */
interface Emitter
{
    /**
     * Emit a single event payload.
     *
     * @param array $payload See structure above. Unknown keys must be tolerated.
     * @return void
     */
    public function __invoke(array $payload): void;
}