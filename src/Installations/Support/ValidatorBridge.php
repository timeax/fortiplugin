<?php

namespace Timeax\FortiPlugin\Installations\Support;

use Throwable;
use Timeax\FortiPlugin\Installations\Contracts\Emitter;

readonly class ValidatorBridge
{
    public function __construct(
        private InstallationLogStore $logStore,
        private string               $installRoot,
        private ?Emitter             $emitter = null,
    ) {}

    /**
     * Forward validator events verbatim to the log store and unified emitter.
     */
    public function emit(array $payload): void
    {
        try {
            $this->logStore->appendValidationEmit($this->installRoot, $payload);
        } catch (Throwable $_) {}
        if ($this->emitter) {
            try { ($this->emitter)($payload); } catch (Throwable $_) {}
        }
    }
}
