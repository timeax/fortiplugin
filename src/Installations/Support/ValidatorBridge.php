<?php

namespace Timeax\FortiPlugin\Installations\Support;

use Timeax\FortiPlugin\Installations\Contracts\Emitter;

class ValidatorBridge
{
    public function __construct(private readonly Emitter $emitter)
    {
    }

    /**
     * Forward validator events verbatim to unified emitter.
     */
    public function emit(array $payload): void
    {
        ($this->emitter)($payload);
    }
}
