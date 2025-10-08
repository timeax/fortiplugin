<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Catalog;

use JsonException;
use Timeax\FortiPlugin\Lib\Obfuscator;
use Timeax\FortiPlugin\Permissions\Cache\KeyBuilder;
use Timeax\FortiPlugin\Permissions\Support\HostConfigNormalizer;

/**
 * Codec/obfuscator catalog sourced from Obfuscator::availableGroups().
 *
 * Obfuscator::availableGroups() is expected to return:
 *   [ groupName => [ phpFunctionName => wrapperName, ... ], ... ]
 *
 * We present it as:
 *   [ groupName => string[] phpFunctionName, ... ]
 */
final class CodecCatalog
{
    /** @var array<string,string[]> group => methods[] */
    private array $groups = [];

    public function __construct()
    {
        $this->boot();
    }

    /** @return array<string,string[]> */
    public function groups(): array
    {
        return $this->groups;
    }

    public function hasGroup(string $group): bool
    {
        return isset($this->groups[$group]);
    }

    /** @return string[] methods for a group (empty if unknown) */
    public function methodsFor(string $group): array
    {
        return $this->groups[$group] ?? [];
    }

    /** @return string[] flattened unique methods across all groups */
    public function allMethods(): array
    {
        $all = [];
        foreach ($this->groups as $methods) {
            foreach ($methods as $m) {
                $all[] = $m;
            }
        }
        $all = array_values(array_unique($all));
        sort($all, SORT_STRING);
        return $all;
    }

    /**
     * @throws JsonException
     */
    public function revision(): string
    {
        return KeyBuilder::fromCapabilities($this->groups);
    }

    /* ------------------------ internals ------------------------ */

    private function boot(): void
    {
        if (!class_exists(Obfuscator::class) || !method_exists(Obfuscator::class, 'availableGroups')) {
            $this->groups = [];
            return;
        }

        $this->groups = HostConfigNormalizer::codecGroupsFromObfuscatorMap(Obfuscator::availableGroups());
    }
}