<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Autoload;

final readonly class PluginAutoloader
{
    public function __construct(
        private ComposerLoaderResolver $resolver,
        private Psr4RegistryStore      $store,
    ) {}

    public function register(): void
    {
        if (!config('fortiplugin.autoload_enabled', true)) {
            return;
        }

        $loader = $this->resolver->resolve();
        if ($loader === null) {
            return;
        }

        $registry = $this->store->read();
        $plugins = $registry['plugins'] ?? [];

        if (!is_array($plugins) || $plugins === []) {
            return;
        }

        foreach ($plugins as $pluginMeta) {
            $prefixes = $pluginMeta['prefixes'] ?? null;
            if (!is_array($prefixes) || $prefixes === []) {
                continue;
            }

            foreach ($prefixes as $prefix => $paths) {
                if (!is_string($prefix) || trim($prefix) === '') {
                    continue;
                }

                $pathsArr = is_array($paths) ? $paths : [$paths];

                // Normalize + filter (cheap, prevents autoload drag)
                $clean = [];
                foreach ($pathsArr as $p) {
                    if (!is_string($p)) {
                        continue;
                    }

                    $p = trim($p);
                    if ($p === '') {
                        continue;
                    }

                    $p = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $p);
                    $p = rtrim($p, DIRECTORY_SEPARATOR);

                    $real = @realpath($p);
                    if ($real === false || !is_dir($real)) {
                        continue;
                    }

                    $clean[$real] = true; // de-dupe
                }

                if ($clean === []) {
                    continue;
                }

                // Register into Composer autoloader (append=false)
                $loader->addPsr4($prefix, array_keys($clean), false);
            }
        }
    }
}
