<?php /** @noinspection PhpUnusedLocalVariableInspection */
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Support;

use Throwable;
use Timeax\FortiPlugin\Installations\Events\InstallationEvent;

/**
 * EmitterMux - Single gate for all installer and validation emits.
 *
 * All installation emits flow through EmitterMux; Laravel events are dispatched
 * only for emits with an explicit event key.
 *
 * Responsibilities:
 * - Persist ALL emits into installation.json via InstallationLogStore
 * - Conditionally dispatch Laravel InstallationEvent (best-effort, non-blocking)
 */
final readonly class EmitterMux
{
    public function __construct(
        private InstallationLogStore $logStore
    ) {}

    /**
     * Emit an installer event.
     *
     * @param array $payload
     * @throws \JsonException
     */
    public function emitInstaller(array $payload): void
    {
        // 1) Always persist first
        $this->logStore->appendInstallerEmit($payload);

        // 2) Conditionally dispatch Laravel event (best-effort)
        $this->dispatchEvent($payload, 'installer');
    }

    /**
     * Emit a validation event.
     *
     * @param array $payload
     * @throws \JsonException
     */
    public function emitValidation(array $payload): void
    {
        // 1) Always persist first
        $this->logStore->appendValidationEmit($payload);

        // 2) Conditionally dispatch Laravel event (best-effort)
        $this->dispatchEvent($payload, 'validation');
    }

    /**
     * Get a callable for installer emits.
     *
     * @return callable(array):void
     */
    public function installerCallable(): callable
    {
        return fn(array $payload) => $this->emitInstaller($payload);
    }

    /**
     * Get a callable for validation emits.
     *
     * @return callable(array):void
     */
    public function validationCallable(): callable
    {
        return fn(array $payload) => $this->emitValidation($payload);
    }

    /**
     * Dispatch Laravel event if payload contains a non-empty 'event' key.
     * Best-effort: swallows all exceptions.
     *
     * @param array $payload
     * @param string $channel
     */
    private function dispatchEvent(array $payload, string $channel): void
    {
        // Only dispatch if payload has an explicit 'event' key
        $eventKey = $payload['event'] ?? null;
        if (!is_string($eventKey) || $eventKey === '') {
            return;
        }

        // Best-effort dispatch - swallow ALL exceptions
        try {
            event(new InstallationEvent(payload: $payload, channel: $channel));
        } catch (Throwable $e) {
            // Swallow - event dispatch failure MUST NOT break installation
        }
    }
}