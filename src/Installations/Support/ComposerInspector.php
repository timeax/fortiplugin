<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Support;

use RuntimeException;
use Timeax\FortiPlugin\Installations\DTO\PackageEntry;
use Timeax\FortiPlugin\Installations\DTO\ComposerPlan;
use Timeax\FortiPlugin\Installations\Enums\PackageStatus;

/**
 * Reads host composer.lock + plugin composer.json to compute foreign package map and plan.
 */
final readonly class ComposerInspector
{
    public function __construct(private AtomicFilesystem $fs)
    {
    }

    /** @return array<string,PackageEntry> */
    public function collectPackages(string $hostComposerLock, string $pluginComposerJson): array
    {
        $fs = $this->fs->fs();
        if (!$fs->exists($hostComposerLock)) {
            throw new RuntimeException("composer.lock not found at $hostComposerLock");
        }
        if (!$fs->exists($pluginComposerJson)) {
            throw new RuntimeException("plugin composer.json not found at $pluginComposerJson");
        }

        $lock = $fs->readJson($hostComposerLock);
        $installed = array_column((array)($lock['packages'] ?? []), 'name');
        $installedDev = array_column((array)($lock['packages-dev'] ?? []), 'name');
        $hostSet = array_fill_keys(array_merge($installed, $installedDev), true);

        $pj = $fs->readJson($pluginComposerJson);
        $requires = array_keys((array)($pj['require'] ?? []));
        $requiresDev = array_keys((array)($pj['require-dev'] ?? []));
        $pluginSet = array_unique(array_merge($requires, $requiresDev));

        $out = [];
        foreach ($pluginSet as $name) {
            $isForeign = !isset($hostSet[$name]);
            $out[$name] = new PackageEntry(
                name: $name,
                is_foreign: $isForeign,
                status: PackageStatus::UNVERIFIED
            );
        }
        return $out;
    }

    public function plan(array $packages): ComposerPlan
    {
        $actions = [];
        $coreConflicts = [];

        foreach ($packages as $name => $entry) {
            if (!$entry instanceof PackageEntry) {
                throw new RuntimeException("Package map must contain PackageEntry instances");
            }
            $actions[$name] = $entry->is_foreign ? 'add' : 'skip';
        }

        // Core collision hints (conservative): flag if plugin references these at all.
        foreach (['php', 'laravel/framework'] as $core) {
            if (isset($actions[$core])) {
                $coreConflicts[] = $core;
                $actions[$core] = 'conflict';
            }
        }

        return new ComposerPlan(actions: $actions, core_conflicts: $coreConflicts);
    }
}