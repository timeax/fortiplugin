<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Support;

use Illuminate\Contracts\Bus\Dispatcher;

/**
 * Enqueues background file scans (optionally propagates an installer token).
 */
final readonly class BackgroundScanDispatcher
{
    public function __construct(private Dispatcher $bus) {}

    /**
     * @param int|string              $zipId
     * @param array<int,string>       $files
     * @param array<string,mixed>     $context  (plugin_dir, actor, run_id, validator_config_hash, ...)
     * @param string|null             $token    Opaque installer token to surface to UI (optional)
     */
    public function enqueue(int|string $zipId, array $files, array $context = [], ?string $token = null): void
    {
        if ($token !== null) {
            $context['token'] = $token; // make available to the job for emitting
        }

        $job = new Jobs\BackgroundFileScanJob((string)$zipId, array_values(array_unique($files)), $context);
        $this->bus->dispatch($job);
    }
}