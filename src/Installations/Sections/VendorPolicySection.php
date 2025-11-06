<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Sections;

use JsonException;
use RuntimeException;
use Timeax\FortiPlugin\Installations\Enums\VendorMode;
use Timeax\FortiPlugin\Installations\Support\AtomicFilesystem;
use Timeax\FortiPlugin\Installations\Support\ComposerInspector;
use Timeax\FortiPlugin\Installations\InstallerPolicy;
use Timeax\FortiPlugin\Installations\DTO\PackageEntry;
use Timeax\FortiPlugin\Installations\Enums\PackageStatus;
use Timeax\FortiPlugin\Installations\Support\InstallationLogStore;

/**
 * VendorPolicySection
 *
 * Enforces vendor policy and summarizes package usage.
 * Persists its rich block via InstallationLogStore→writeSection('vendor_policy', …).
 */
final readonly class VendorPolicySection
{
    public function __construct(
        private InstallerPolicy      $policy,
        private AtomicFilesystem     $afs,
        private ComposerInspector    $composer,
        private InstallationLogStore $log
    )
    {
    }

    /**
     * @param string $pluginDir Unpacked plugin root
     * @param string|null $hostComposerLock Absolute path to host composer.lock (optional)
     * @param callable(array):void|null $emit Verbatim emitter
     *
     * @return array{
     *   vendor_policy: array{mode:'STRIP_BUNDLED_VENDOR'|'ALLOW_BUNDLED_VENDOR'},
     *   meta: array<string,mixed>
     * }
     * @throws JsonException
     * @noinspection PhpUndefinedClassInspection
     */
    public function run(
        string    $pluginDir,
        string   $hostComposerLock = null,
        ?callable $emit = null
    ): array
    {
        $mode = $this->policy->getVendorMode(); // VendorMode enum

        $pluginComposer = rtrim($pluginDir, "\\/") . '/composer.json';

        // ---- Collect package map (PackageEntry[]) using only provided API
        $hostLockPresent = is_string($hostComposerLock)
            && $hostComposerLock !== ''
            && $this->afs->fs()->exists($hostComposerLock);

        $emit && $emit([
            'title' => 'VendorPolicy: Inspect',
            'description' => $hostLockPresent
                ? 'Collecting packages using host composer.lock'
                : 'composer.lock not found — treating all plugin requirements as foreign',
            'error' => null,
            'stats' => [
                'filePath' => $hostLockPresent ? $hostComposerLock : $pluginComposer,
                'size' => $hostLockPresent ? $this->afs->fs()->fileSize($hostComposerLock) : $this->afs->fs()->fileSize($pluginComposer),
            ],
            'meta' => ['phase' => 'vendor_policy', 'op' => 'collect_packages']
        ]);

        /** @var array<string,PackageEntry> $packages */
        if ($hostLockPresent) {
            $packages = $this->composer->collectPackages($hostComposerLock, $pluginComposer);
        } else {
            $packages = $this->fallbackCollectFromPlugin($pluginComposer);
        }

        // ---- Derive lists & persistable meta
        $alreadyPresent = [];
        $foreign = [];
        $packagesMeta = [];

        foreach ($packages as $name => $entry) {
            if (!$entry instanceof PackageEntry) {
                throw new RuntimeException("ComposerInspector must return PackageEntry map");
            }
            if ($entry->is_foreign) $foreign[] = $name;
            else $alreadyPresent[] = $name;

            $packagesMeta[$name] = [
                'is_foreign' => $entry->is_foreign,
                'status' => ($entry->status ?? PackageStatus::UNVERIFIED)->value,
            ];
        }

        // ---- Vendor mode enforcement
        $pluginVendor = rtrim($pluginDir, "\\/") . '/vendor';
        $hasVendorDir = $this->afs->fs()->isDirectory($pluginVendor);
        $stripped = false;
        $notes = [];

        if ($mode === VendorMode::STRIP_BUNDLED_VENDOR && $hasVendorDir) {
            $parkTo = rtrim($pluginDir, "\\/") . '/.internal/stripped/vendor-' . date('YmdHis');
            $this->afs->ensureParentDirectory($parkTo);

            try {
                $this->afs->fs()->rename($pluginVendor, $parkTo);
                $stripped = true;

                $emit && $emit([
                    'title' => 'VendorPolicy: Stripped vendor',
                    'description' => 'Plugin vendor/ moved out per policy',
                    'error' => null,
                    'stats' => ['filePath' => $pluginVendor, 'size' => null],
                    'meta' => ['phase' => 'vendor_policy', 'op' => 'strip_vendor', 'parked_to' => $parkTo]
                ]);
            } catch (RuntimeException $e) {
                $notes[] = 'Failed to move vendor/: ' . $e->getMessage();

                $emit && $emit([
                    'title' => 'VendorPolicy: Strip failed',
                    'description' => $e->getMessage(),
                    'error' => ['detail' => 'rename_failed', 'count' => 1],
                    'stats' => ['filePath' => $pluginVendor, 'size' => null],
                    'meta' => ['phase' => 'vendor_policy', 'op' => 'strip_vendor_failed']
                ]);
            }
        } else {
            $emit && $emit([
                'title' => 'VendorPolicy: Keep vendor',
                'description' => $hasVendorDir ? 'Bundled vendor retained per policy' : 'No bundled vendor found',
                'error' => null,
                'stats' => ['filePath' => $pluginVendor, 'size' => null],
                'meta' => ['phase' => 'vendor_policy', 'op' => 'keep_vendor']
            ]);
        }

        // ---- Build block and persist via InstallationLogStore
        $block = [
            'mode' => $mode->name,  // matches TVendorPolicy
            'plugin_has_vendor' => $hasVendorDir,
            'stripped' => $stripped,
            'packages' => $packagesMeta,       // for UI + later DbPersistSection
            'already_present' => array_values($alreadyPresent),
            'foreign' => array_values($foreign),
            'host_lock_present' => $hostLockPresent,
            'notes' => $notes,
        ];
        $this->log->writeSection('vendor_policy', $block);

        // ---- Return DTO-ready summary + meta
        return [
            'vendor_policy' => ['mode' => $mode->name],
            'meta' => $block,
        ];
    }

    /**
     * Fallback when host composer.lock is unavailable:
     * read plugin composer.json and mark all requires (+dev) as foreign/unverified.
     *
     * @param string $pluginComposerJson
     * @return array<string,PackageEntry>
     */
    private function fallbackCollectFromPlugin(string $pluginComposerJson): array
    {
        $fs = $this->afs->fs();
        if (!$fs->exists($pluginComposerJson)) {
            throw new RuntimeException("plugin composer.json not found at $pluginComposerJson");
        }
        $pj = $fs->readJson($pluginComposerJson);
        $req = array_keys((array)($pj['require'] ?? []));
        $reqD = array_keys((array)($pj['require-dev'] ?? []));
        $names = array_unique(array_merge($req, $reqD));

        $out = [];
        foreach ($names as $name) {
            if ($name === 'php' || str_starts_with($name, 'ext-')) {
                continue;
            }
            $out[$name] = new PackageEntry(
                name: $name,
                is_foreign: true,
                status: PackageStatus::UNVERIFIED
            );
        }
        return $out;
    }
}