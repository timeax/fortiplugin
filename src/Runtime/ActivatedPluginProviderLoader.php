<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Runtime;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final readonly class ActivatedPluginProviderLoader
{
    public function __construct(private Application $app)
    {
    }

    public function registerAll(): void
    {
        $map = $this->readProvidersMap(); // alias => [ProviderFqcn...]

        foreach ($map as $alias => $providers) {
            if (!is_array($providers)) {
                continue;
            }

            // If you need plugin autoloading, do it here per $alias before class_exists()

            foreach ($providers as $provider) {
                if (!is_string($provider) || $provider === '') {
                    continue;
                }

                if (!class_exists($provider)) {
                    Log::warning('FortiPlugin: provider class not found', [
                        'plugin' => $alias,
                        'provider' => $provider,
                        'path' => $this->providersPath(),
                    ]);
                    continue;
                }

                $this->app->register($provider);
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