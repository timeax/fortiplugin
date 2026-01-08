<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * InstallationEvent
 *
 * Dispatched by EmitterMux for installation and validation emits that contain
 * an explicit 'event' key in their payload.
 *
 * All installation emits flow through EmitterMux; Laravel events are dispatched
 * only for emits with an explicit event key.
 */
final class InstallationEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param array $payload Full unified emit payload
     * @param string $channel 'installer' or 'validation'
     */
    public function __construct(
        public array  $payload,
        public string $channel,
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
