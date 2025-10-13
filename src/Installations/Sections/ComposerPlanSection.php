<?php

namespace Timeax\FortiPlugin\Installations\Sections;

use Throwable;
use Timeax\FortiPlugin\Installations\DTO\ComposerPlan;
use Timeax\FortiPlugin\Installations\Enums\PackageStatus;
use Timeax\FortiPlugin\Installations\Support\ComposerInspector;
use Timeax\FortiPlugin\Installations\Support\InstallationLogStore;

class ComposerPlanSection
{
    /**
     * Build a dry composer plan and packages map, then persist to installation.json.
     * Returns associative array ['actions'=>..., 'core_conflicts'=>..., 'packages'=>map].
     */
    public function run(
        ComposerInspector $inspector,
        InstallationLogStore $logStore,
        string $stagingRoot,
        string $installRoot,
        ?callable $emit = null
    ): array {
        $requires = $inspector->readPluginRequires($stagingRoot);               // name => constraint
        $hostLocked = $inspector->readHostLockedPackages();                      // name => version

        $actions = [];
        $coreConflicts = [];
        $packages = [];

        foreach ($requires as $name => $constraint) {
            // rudimentary: if host has the package at any version, mark skip; otherwise add
            $hostHas = array_key_exists($name, $hostLocked);
            $actions[$name] = $hostHas ? 'skip' : 'add';

            // core conflicts: if is core package and constraint not trivially satisfied by presence
            if (!$hostHas && $inspector->isCorePackage($name)) {
                $coreConflicts[] = $name;
            }

            $packages[$name] = [
                'is_foreign' => !$hostHas,
                'status' => $hostHas ? PackageStatus::VERIFIED->value : PackageStatus::UNVERIFIED->value,
            ];
        }

        // Persist to installation.json
        $logStore->setComposerPlan($installRoot, [
            'actions' => $actions,
            'core_conflicts' => $coreConflicts,
        ]);
        $logStore->setPackages($installRoot, $packages);

        // Emit installer event
        if ($emit) {
            try {
                $emit([
                    'title' => 'Installer: Composer Plan',
                    'description' => 'Dry composer plan computed',
                    'error' => null,
                    'stats' => ['filePath' => null, 'size' => null],
                    'meta' => [
                        'counts' => ['requires' => count($requires), 'foreign' => count(array_filter($packages, static fn($p) => ($p['is_foreign'] ?? false)))],
                        'core_conflicts' => $coreConflicts,
                    ],
                ]);
            } catch (Throwable $_) {}
        }

        return [
            'actions' => $actions,
            'core_conflicts' => $coreConflicts,
            'packages' => $packages,
        ];
    }
}
