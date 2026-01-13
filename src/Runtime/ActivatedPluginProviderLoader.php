<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Runtime;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;
use Timeax\FortiPlugin\Installations\Lifecycle\Deactivation\Deactivator;
use Timeax\FortiPlugin\Services\PluginLifecycleService;
use Timeax\FortiPlugin\Support\AuditLogger;

final readonly class ActivatedPluginProviderLoader
{
    public function __construct(private Application $app)
    {
    }

    public function registerAll(): void
    {
        $map = $this->readProvidersMap(); // alias => [ProviderFqcn...]

        foreach ($map as $alias => $providers) {
            foreach ($providers as $provider) {
                try {
                    if (!is_string($provider) || $provider === '') {
                        continue;
                    }

                    // Will autoload the class (may include file)
                    if (!class_exists($provider)) {
                        throw new \RuntimeException("Provider class not found: {$provider}");
                    }

                    app()->register($provider); // may run register() and boot() immediately if app already booted
                } catch (Throwable $e) {
                    // 1) log it
                    AuditLogger::log('provider_registry', [
                        'plugin' => $alias,
                        'provider' => $provider,
                        'error' => $e->getMessage(),
                        'message' => 'Failed to register provider'
                    ]);

                    // 2) mark plugin problematic + deactivate (and remove from registries)
                    // IMPORTANT: do this in a "best effort" way so failure here doesn't break the request.
                    try {
                        // disablePlugin($alias) should:
                        // - set plugin status inactive/problem
                        // - remove alias from providers/ui/routes/autoload registries
                    } catch (Throwable $inner) {
                        AuditLogger::log('disabling_failed', [
                            'plugin' => $alias,
                            'error' => $inner->getMessage(),
                            'message' => 'FortiPlugin could not persist disable state'
                        ]);
                    }

                    // 3) stop processing more providers for THIS plugin, continue to next plugin
                    break;
                }
            }
        }
    }

    private function readProvidersMap(): array
    {
        $path = $this->providersPath();

        if (!is_file($path)) {
            return [];
        }

        $raw = file_get_contents($path);
        if ($raw === false || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        if (!is_array($decoded)) {
            throw new RuntimeException("FortiPlugin providers map is invalid JSON: {$path}");
        }

        return $decoded;
    }

    private function providersPath(): string
    {
        return base_path('bootstrap/fortiplugin.providers.json');
    }
}