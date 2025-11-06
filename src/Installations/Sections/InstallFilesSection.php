<?php /** @noinspection PhpUnusedLocalVariableInspection */
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Sections;

use JsonException;
use RuntimeException;
use Throwable;
use Timeax\FortiPlugin\Installations\DTO\InstallMeta;
use Timeax\FortiPlugin\Installations\Enums\VendorMode;
use Timeax\FortiPlugin\Installations\InstallerPolicy;
use Timeax\FortiPlugin\Installations\Support\InstallationLogStore;
use Timeax\FortiPlugin\Installations\Support\AtomicFilesystem;

/**
 * InstallFilesSection
 *
 * Responsibilities
 * - Copy staged plugin files into the host install directory.
 * - Respect InstallerPolicy::getVendorMode():
 *    • STRIP_BUNDLED_VENDOR → exclude vendor/ from copy
 *    • ALLOW_BUNDLED_VENDOR → copy vendor/ as-is
 * - Persist a concise "install_files" section into installation.json.
 * - Emit terse installer events (start/ok/fail).
 *
 * Non-goals
 * - Running composer install/update (handled by higher layers).
 * - Activation or DB persistence (handled by other sections).
 */
final readonly class InstallFilesSection
{
    public function __construct(
        private InstallerPolicy      $policy,
        private InstallationLogStore $log,
        private AtomicFilesystem     $afs,
    ) {}

    /**
     * @param InstallMeta $meta Canonical meta (paths, psr4_root, placeholder_name, etc.)
     * @param string $stagingPluginRoot Absolute path to staged/unpacked plugin root
     * @param callable|null $emit Optional installer-level emitter fn(array $payload): void
     * @return array{status:'ok'|'fail', dest?:string, vendor_mode?:string}
     * @throws JsonException
     * @noinspection PhpUndefinedClassInspection
     */
    public function run(
        InstallMeta $meta,
        string $stagingPluginRoot,
        ?callable $emit = null
    ): array {
        $dest = (string)($meta->paths['install'] ?? '');
        $vendorMode = $this->policy->getVendorMode();

        // Basic guards
        $emit && $emit([
            'title' => 'INSTALL_FILES_START',
            'description' => 'Copying plugin files into install directory',
            'meta' => [
                'placeholder_name' => $meta->placeholder_name,
                'source' => $stagingPluginRoot,
                'dest' => $dest,
                'vendor_mode' => $vendorMode->value,
            ],
        ]);
        $this->log->appendInstallerEmit([
            'title' => 'INSTALL_FILES_START',
            'description' => 'Copying plugin files into install directory',
            'meta' => [
                'placeholder_name' => $meta->placeholder_name,
                'source' => $stagingPluginRoot,
                'dest' => $dest,
                'vendor_mode' => $vendorMode->value,
            ],
        ]);

        try {
            if ($dest === '') {
                throw new RuntimeException('Install path is missing in meta.paths.install');
            }
            if (!$this->afs->fs()->exists($stagingPluginRoot)) {
                throw new RuntimeException("Staging root not found: $stagingPluginRoot");
            }

            // Ensure destination directory exists
            $this->afs->fs()->ensureDirectory($dest);

            // Build copy filter based on vendor mode (exclude vendor/ when stripping)
            $stripVendor = ($vendorMode === VendorMode::STRIP_BUNDLED_VENDOR);
            $filter = function (string $relativePath) use ($stripVendor): bool {
                if ($stripVendor) {
                    // prevent copying vendor directory and its contents
                    if ($relativePath === 'vendor' || str_starts_with($relativePath, 'vendor/')) {
                        return false;
                    }
                }
                return true;
            };

            // Perform the tree copy
            $this->afs->fs()->copyTree($stagingPluginRoot, $dest, $filter);

            // Persist a concise install_files block
            $this->log->writeSection('install_files', [
                'source'       => $stagingPluginRoot,
                'dest'         => $dest,
                'vendor_mode'  => $vendorMode->value,
                'vendor_stripped' => $stripVendor,
            ]);

            $ok = [
                'title' => 'INSTALL_FILES_OK',
                'description' => 'Plugin files copied successfully',
                'meta' => [
                    'dest' => $dest,
                    'vendor_mode' => $vendorMode->value,
                    'vendor_stripped' => $stripVendor,
                ],
            ];
            $emit && $emit($ok);
            $this->log->appendInstallerEmit($ok);

            return ['status' => 'ok', 'dest' => $dest, 'vendor_mode' => $vendorMode->value];
        } catch (Throwable $e) {
            // Persist failure context (best-effort)
            try {
                $this->log->writeSection('install_files', [
                    'error' => $e->getMessage(),
                    'source' => $stagingPluginRoot,
                    'dest' => $dest,
                    'vendor_mode' => $vendorMode->value,
                ]);
            } catch (Throwable $_) {}

            $fail = [
                'title' => 'INSTALL_FILES_FAIL',
                'description' => 'Failed to copy plugin files',
                'meta' => [
                    'error' => $e->getMessage(),
                    'source' => $stagingPluginRoot,
                    'dest' => $dest,
                    'vendor_mode' => $vendorMode->value,
                ],
            ];
            $emit && $emit($fail);
            $this->log->appendInstallerEmit($fail);

            return ['status' => 'fail'];
        }
    }
}