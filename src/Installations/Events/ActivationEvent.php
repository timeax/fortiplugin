<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * ActivationEvent
 *
 * Dispatched by Activator for activation emits that contain
 * an explicit 'event' key in their payload.
 *
 * Similar to InstallationEvent but for activation operations.
 */
final class ActivationEvent
{
    use Dispatchable, SerializesModels;

    /**
     * @param array $payload Full unified emit payload
     */
    public function __construct(
        public array $payload,
    ) {
    }

    /**
     * Get the event key from the payload.
     *
     * @return string
     */
    public function key(): string
    {
        return (string)($this->payload['event'] ?? '');
    }
}
