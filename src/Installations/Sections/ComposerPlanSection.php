<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Sections;

use Throwable;
use Timeax\FortiPlugin\Installations\Support\ComposerInspector;
use Timeax\FortiPlugin\Installations\Support\InstallationLogStore;
use Timeax\FortiPlugin\Installations\Support\EmitsEvents;
use Timeax\FortiPlugin\Installations\Support\Events;
use Timeax\FortiPlugin\Installations\Support\ErrorCodes;

/**
 * ComposerPlanSection
 *
 * Responsibilities
 * - Read host composer.lock and plugin composer.json.
 * - Build the per-package “foreign map” (is_foreign + initial UNVERIFIED status).
 * - Compute a conservative Composer plan (add/skip + core_conflicts).
 * - Persist to installation.json under "composer_plan":
 *     {
 *       "packages": { "<name>": { is_foreign, status } ... },
 *       "plan": { actions: {...}, core_conflicts: [...] }
 *     }
 *
 * Notes
 * - This section does NOT execute Composer or modify vendor code.
 * - It merely prepares data for the host UI/flows (and later, optional scans of foreign packages).
 */
final class ComposerPlanSection
{
    use EmitsEvents;

    public function __construct(
        private readonly InstallationLogStore $log,
        private readonly ComposerInspector    $inspector,
    )
    {
    }

    /**
     * @param string $pluginDir Plugin root directory (must contain composer.json)
     * @param string|null $hostComposerLock Absolute path to host composer.lock; if null, defaults to getcwd().'/composer.lock'
     * @param callable|null $emit Optional installer-level emitter fn(array $payload): void
     * @return array{
     *   status: 'ok'|'fail',
     *   packages?: array<string, array{is_foreign:bool,status:string}>,
     *   plan?: array{actions: array<string,string>, core_conflicts: list<string>}
     * }
     */
    public function run(
        string    $pluginDir,
        ?string   $hostComposerLock = null,
        ?callable $emit = null
    ): array
    {
        $emit && $emit(['title' => 'COMPOSER_PLAN_START', 'description' => 'Collecting packages & computing plan']);

        $pluginComposer = rtrim($pluginDir, "\\/") . DIRECTORY_SEPARATOR . 'composer.json';
        $hostLock = $hostComposerLock ?: (getcwd() . DIRECTORY_SEPARATOR . 'composer.lock');

        try {
            // 1) Collect package map (foreign vs host-present)
            $packages = $this->inspector->collectPackages($hostLock, $pluginComposer); // array<string,PackageEntry>

            // 2) Compute plan (actions + core_conflicts)
            $plan = $this->inspector->plan($packages); // ComposerPlan

            // 3) Persist to installation.json under "composer_plan"
            $this->log->writeSection('composer_plan', [
                'packages' => array_map(static fn($e) => $e->toArray(), $packages),
                'plan' => $plan->toArray(),
            ]);

            $emit && $emit(['title' => 'COMPOSER_PLAN_COMPUTED', 'description' => 'Composer plan persisted', 'meta' => [
                'path' => $this->log->path(),
                'packages' => count($packages),
                'core_conflicts' => $plan->core_conflicts,
            ]]);

            return [
                'status' => 'ok',
                'packages' => array_map(static fn($e) => $e->toArray(), $packages),
                'plan' => $plan->toArray(),
            ];
        } catch (Throwable $e) {
            // Emit a concise failure and return
            $this->emitFail(
                Events::COMPOSER_PLAN_FAIL,
                ErrorCodes::FILESYSTEM_READ_FAILED,
                'Failed to compute Composer plan',
                [
                    'exception' => $e->getMessage(),
                    'host_lock' => $hostLock,
                    'plugin_composer' => $pluginComposer,
                ]
            );
            $emit && $emit(['title' => 'COMPOSER_PLAN_FAIL', 'description' => 'Composer plan failed', 'meta' => [
                'error' => $e->getMessage()
            ]]);

            return ['status' => 'fail'];
        }
    }
}