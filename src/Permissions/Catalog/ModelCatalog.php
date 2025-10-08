<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Catalog;

use JsonException;
use Timeax\FortiPlugin\Permissions\Cache\KeyBuilder;
use Timeax\FortiPlugin\Permissions\Support\HostConfigNormalizer;

/**
 * Read-only catalog of host models (alias → FQCN + relations + column policy).
 * Expected config shape (example):
 *
 *  'fortiplugin-maps.models' => [
 *     'user' => [
 *        'map' => 'App\\Models\\User',
 *        'relations' => ['posts' => 'post', 'profile' => 'profile'],
 *        'columns' => [
 *           'all' => ['id','name','email'],
 *           'writable' => ['name']
 *        ]
 *     ],
 *     // ...
 *  ]
 */
final class ModelCatalog
{
    /** @var array<string,array{map:string,relations:array<string,string>,columns:array{all:?array,writable:?array}}
     * @noinspection PhpUndefinedClassInspection
     */
    private array $models = [];

    /** @var array<string,string> FQCN => alias */
    private array $fqcnToAlias = [];

    public function __construct(?array $config = null)
    {
        $this->boot($config ?? $this->readConfig('fortiplugin-maps.models', []));
    }

    /** @return array<string,array{map:string,relations:array<string,string>,columns:array{all:?array,writable:?array}}
     * @noinspection PhpUndefinedClassInspection
     */
    public function all(): array
    {
        return $this->models;
    }

    public function hasAlias(string $alias): bool
    {
        return isset($this->models[$alias]);
    }

    public function aliasForFqcn(string $fqcn): ?string
    {
        return $this->fqcnToAlias[$fqcn] ?? null;
    }

    public function fqcnForAlias(string $alias): ?string
    {
        return $this->models[$alias]['map'] ?? null;
    }

    /** @return array{all:?string[],writable:?string[]}
     * @noinspection PhpUndefinedClassInspection
     */
    public function columnsForAlias(string $alias): array
    {
        $cols = $this->models[$alias]['columns'] ?? ['all' => null, 'writable' => null];
        return ['all' => $cols['all'] ?? null, 'writable' => $cols['writable'] ?? null];
    }

    /** @return array<string,string> */
    public function relationsForAlias(string $alias): array
    {
        return $this->models[$alias]['relations'] ?? [];
    }

    /** Stable hash of the normalized catalog for cache busting/ETag
     * @throws JsonException
     */
    public function revision(): string
    {
        return KeyBuilder::fromCapabilities($this->models);
    }

    /* ------------------------ internals ------------------------ */

    private function boot(array $models): void
    {
        $this->models = HostConfigNormalizer::models($models);
        $this->fqcnToAlias = [];
        foreach ($this->models as $alias => $def) {
            $this->fqcnToAlias[$def['map']] = $alias;
        }
    }

    /** @return string[] */
    private function uniqueStrings(array $list): array
    {
        $list = array_values(array_filter($list, static fn($v) => is_string($v) && $v !== ''));
        $list = array_values(array_unique(array_map('strval', $list)));
        sort($list, SORT_STRING);
        return $list;
    }

    private function readConfig(string $key, mixed $default): mixed
    {
        return function_exists('config') ? config($key, $default) : $default;
    }
}