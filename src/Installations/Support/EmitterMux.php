<?php

namespace Timeax\FortiPlugin\Installations\Support;

use Timeax\FortiPlugin\Installations\Contracts\Emitter;

class EmitterMux
{
    /** @var Emitter[] */
    private array $sinks = [];

    public function __construct(Emitter ...$sinks)
    {
        $this->sinks = $sinks;
    }

    public function add(Emitter $emitter): void
    {
        $this->sinks[] = $emitter;
    }

    public function emit(array $payload): void
    {
        foreach ($this->sinks as $sink) {
            $sink($payload);
        }
    }
}
