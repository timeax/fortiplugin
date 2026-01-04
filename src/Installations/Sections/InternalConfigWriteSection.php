<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Sections;

use Illuminate\Support\Str;
use Throwable;
use Timeax\FortiPlugin\Installations\DTO\InstallMeta;
use Timeax\FortiPlugin\Installations\Support\AtomicFilesystem;
use Timeax\FortiPlugin\Installations\Support\InstallationLogStore;
use Timeax\FortiPlugin\Installations\Support\Psr4Checker;
use Timeax\FortiPlugin\Traits\Stubber;

final readonly class InternalConfigWriteSection
{
    use Stubber;

    public function __construct(
        private InstallationLogStore $log,
        private AtomicFilesystem     $afs,
        private Psr4Checker          $psr4,
    )
    {
    }


    public function run(
        InstallMeta $meta,
        string      $stagingPluginRoot,
        int         $pluginId,
        callable    $emit,
    ): array
    {
        $target = rtrim($stagingPluginRoot, "\\/")
            . DIRECTORY_SEPARATOR . '.internal'
            . DIRECTORY_SEPARATOR . 'Config.php';

        $emit([
            'title' => 'INTERNAL_CONFIG_START',
            'description' => 'Writing .internal/Config.php from stub',
            'meta' => [
                'staging' => $stagingPluginRoot,
                'target' => $target,
                'plugin_id' => $pluginId,
            ],
        ]);

        try {
            // Namespace prefix (project standard helper)
            [$nsPrefix, /* $dirRel */] = $this->psr4->expected($meta->psr4_root, $meta->placeholder_name);
            $pluginNamespace = rtrim($nsPrefix, '\\'); // remove trailing slash from helper

            // For now: no verification_block exists in logs (trace confirms). Use fallback.
            $signatureBlock = "// (signature block not available from installer logs yet)";

            $php = $this->renderStub('config-prod', [
                'SIGNATURE_BLOCK' => $signatureBlock,
                'PLUGIN_NAMESPACE' => $pluginNamespace,
                'PLUGIN_ID' => (string)$pluginId,
                'PLUGIN_ALIAS' => $meta->placeholder_name,
                'PLUGIN_STUDLY' => Str::studly($meta->placeholder_name),
            ]);

            $this->afs->ensureParentDirectory($target);
            $this->afs->fs()->writeAtomic($target, $php);

            $this->log->writeSection('internal_config', [
                'status' => 'ok',
                'path' => $target,
                'plugin_id' => $pluginId,
                'namespace' => $pluginNamespace,
            ]);

            $emit([
                'title' => 'INTERNAL_CONFIG_OK',
                'description' => 'Wrote .internal/Config.php',
                'meta' => ['path' => $target],
            ]);

            return ['status' => 'ok', 'path' => $target];
        } catch (Throwable $e) {
            try {
                $this->log->writeSection('internal_config', [
                    'status' => 'fail',
                    'error' => $e->getMessage(),
                    'path' => $target,
                    'plugin_id' => $pluginId,
                ]);
            } catch (Throwable) {
                // best-effort like other sections
            }

            $emit([
                'title' => 'INTERNAL_CONFIG_FAIL',
                'description' => 'Failed to write .internal/Config.php',
                'meta' => ['error' => $e->getMessage()],
            ]);

            return ['status' => 'fail'];
        }
    }
}
