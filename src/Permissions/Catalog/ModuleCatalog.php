<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Catalog;

use JsonException;
use Timeax\FortiPlugin\Permissions\Cache\KeyBuilder;
use Timeax\FortiPlugin\Permissions\Support\HostConfigNormalizer;

/**
 * Catalog of host modules (alias ↔ FQCN + docs).
 * Expected config:
 *
 *  'fortiplugin-maps.modules' => [
 *      'auth-service' => ['map' => 'App\\Modules\\AuthService\\AuthService', 'docs' => 'https://...'],
 *      // ...
 *  ]
 */
final class ModuleCatalog
{
    /** @var array<string,array{map:string,docs?:string}> */
    private array $modules = [];

    /** @var array<string,string> FQCN => alias */
    private array $fqcnToAlias = [];

    public function __construct(?array $config = null)
    {
        $this->boot($config ?? $this->readConfig());
    }

    /** @return array<string,array{map:string,docs?:string}> */
    public function all(): array
    {
        return $this->modules;
    }

    public function hasAlias(string $alias): bool
    {
        return isset($this->modules[$alias]);
    }

    public function aliasForFqcn(string $fqcn): ?string
    {
        return $this->fqcnToAlias[$fqcn] ?? null;
    }

    public function fqcnForAlias(string $alias): ?string
    {
        return $this->modules[$alias]['map'] ?? null;
    }

    public function docsForAlias(string $alias): ?string
    {
        return $this->modules[$alias]['docs'] ?? null;
    }

    /**
     * @throws JsonException
     */
    public function revision(): string
    {
        return KeyBuilder::fromCapabilities($this->modules);
    }

    /* ------------------------ internals ------------------------ */

    private function boot(array $modules): void
    {
        $this->modules = HostConfigNormalizer::modules($modules);
        $this->fqcnToAlias = [];
        foreach ($this->modules as $alias => $def) {
            $this->fqcnToAlias[$def['map']] = $alias;
        }
    }

    private function readConfig(): mixed
    {
        $default = [];
        return function_exists('config') ? config('fortiplugin-maps.modules', $default) : $default;
    }
}