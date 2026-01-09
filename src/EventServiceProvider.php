<?php

namespace Timeax\FortiPlugin;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return true;
    }

    /**
     * Get the listener directories that should be used to discover events.
     */
    protected function discoverEventsWithin(): array
    {
        return [
            __DIR__ . '/./Installations/Events',
            __DIR__ . '/./Events', // Adjust path to your package's listener folder
        ];
    }
}
