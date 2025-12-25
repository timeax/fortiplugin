<?php

namespace Timeax\FortiPlugin\Autoload;

use Composer\Autoload\ClassLoader;

final class ComposerLoaderResolver
{
    public function resolve(): ?ClassLoader
    {
        if (!class_exists(ClassLoader::class)) {
            return null;
        }

        $loaders = ClassLoader::getRegisteredLoaders();
        if (!$loaders) {
            return null;
        }

        $vendorPath = $this->realpathSafe($this->basePath('vendor'));

        if ($vendorPath) {
            foreach ($loaders as $vendorDir => $loader) {
                if ($this->realpathSafe($vendorDir) === $vendorPath) {
                    return $loader;
                }
            }
        }

        return array_values($loaders)[0] ?? null;
    }

    private function basePath(string $path = ''): string
    {
        if (function_exists('base_path')) {
            return base_path($path);
        }

        $root = getcwd() ?: __DIR__;
        return rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
    }

    private function realpathSafe(string $path): ?string
    {
        $p = @realpath($path);
        return $p !== false ? $p : null;
    }
}
