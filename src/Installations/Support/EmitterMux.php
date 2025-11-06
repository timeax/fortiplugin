<?php /** @noinspection PhpUnusedLocalVariableInspection */
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Support;

use Throwable;
use Timeax\FortiPlugin\Installations\Contracts\Emitter;

/**
 * Forwards emits to host Emitter and mirrors into InstallationLogStore (verbatim for validation).
 */
final readonly class EmitterMux
{
    public function __construct(
        private Emitter              $hostEmitter,
        private InstallationLogStore $logStore
    ) {}

    /** @param array $payload */
    public function emitValidation(array $payload): void
    {
        // Host first (non-throwing contract), then persist verbatim
        try { ($this->hostEmitter)($payload); } catch (Throwable $e) { /* swallow */ }
        $this->logStore->appendValidationEmit($payload);
    }

    /** @param array $payload */
    public function emitInstaller(array $payload): void
    {
        try { ($this->hostEmitter)($payload); } catch (Throwable $e) { /* swallow */ }
        $this->logStore->appendInstallerEmit($payload);
    }

    /** @return callable(array):void */
    public function validationCallable(): callable
    {
        return function (array $payload): void {
            $this->emitValidation($payload);
        };
    }
}