<?php /** @noinspection GrazieInspection */
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Sections;

use JsonException;
use RuntimeException;
use Throwable;
use Timeax\FortiPlugin\Installations\Support\AtomicFilesystem;
use Timeax\FortiPlugin\Installations\Support\InstallationLogStore;
use Timeax\FortiPlugin\Installations\Support\Psr4Checker;

/**
 * ProviderValidationSection
 *
 * Responsibilities
 * - If providers are declared, verify their files exist under the staged plugin root.
 * - Resolution rules:
 *    • Full FQCN: "<psr4_root>\<pluginName>\..." → file under <pluginDir>/...\ .php
 *    • Relative:  "Providers\X\Y"               → <pluginDir>/Providers/X/Y.php
 * - Persist "providers_validation" block in installation.json (declared/ok/missing/map).
 * - Emit start/ok/fail installer events (verbatim through the provided emitter).
 *
 * Non-goals
 * - No class loading or inheritance checks.
 */
final readonly class ProviderValidationSection
{
    public function __construct(
        private InstallationLogStore $log,
        private AtomicFilesystem     $afs,
        private Psr4Checker          $psr4,
    )
    {
    }

    /**
     * Run provider-file presence checks against the staged plugin directory.
     *
     * @param string $pluginDir Absolute path to staged plugin root
     * @param string $pluginName Unique plugin name (namespace segment)
     * @param string $psr4Root Host PSR-4 root (e.g., "Plugins")
     * @param list<string> $providers Values from fortiplugin.json ("providers" array)
     * @param callable|null $emit Optional emitter: fn(array $payload): void
     *
     * @return array{status:'ok'|'fail', missing?:list<string>}
     *
     * @throws JsonException
     * @noinspection PhpUndefinedClassInspection
     */
    public function run(
        string    $pluginDir,
        string    $pluginName,
        string    $psr4Root,
        array     $providers,
        ?callable $emit = null
    ): array
    {
        $pluginDir = rtrim($pluginDir, "\\/");

        if ($pluginDir === '' || !$this->afs->fs()->isDirectory($pluginDir)) {
            throw new RuntimeException('Provider check: valid $pluginDir is required.');
        }

        // Emit start
        $start = [
            'title' => 'PROVIDERS_CHECK_START',
            'description' => 'Validating declared providers exist in staged plugin',
            'meta' => [
                'plugin' => $pluginName,
                'declared_count' => count($providers),
                'staging' => $pluginDir,
            ],
        ];
        $emit && $emit($start);
        $this->log->appendInstallerEmit($start);

        // Quick exit if no providers declared
        if ($providers === []) {
            $this->log->writeSection('providers_validation', [
                'declared' => 0,
                'ok' => 0,
                'missing' => [],
                'files' => [],
            ]);
            $ok = [
                'title' => 'PROVIDERS_CHECK_OK',
                'description' => 'No providers declared',
                'meta' => ['declared' => 0],
            ];
            $emit && $emit($ok);
            $this->log->appendInstallerEmit($ok);
            return ['status' => 'ok'];
        }

        // Namespace prefix computed from host psr4Root + pluginName
        [$nsPrefix, /* $dirRel */] = $this->psr4->expected($psr4Root, $pluginName);

        $fs = $this->afs->fs();
        $missing = [];
        $fileMap = []; // provider => resolved absolute path

        foreach ($providers as $prov) {
            if (!is_string($prov) || $prov === '') {
                $missing[] = $prov;
                continue;
            }

            $relative = $prov;
            if (str_starts_with($prov, $nsPrefix)) {
                // Strip "<psr4Root>\<pluginName>\"
                $relative = substr($prov, strlen($nsPrefix));
            }
            $relative = ltrim($relative, '\\/');

            $path = $pluginDir . DIRECTORY_SEPARATOR
                . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';

            $fileMap[$prov] = $path;

            if (!$fs->exists($path) || !$fs->isFile($path)) {
                $missing[] = $prov;
            }
        }

        // Persist results
        $doc = [
            'declared' => count($providers),
            'ok' => count($providers) - count($missing),
            'missing' => $missing,
            'files' => $fileMap,
        ];
        try {
            $this->log->writeSection('providers_validation', $doc);
        } catch (Throwable) {
            // best-effort; keep flowing
        }

        if ($missing !== []) {
            $fail = [
                'title' => 'PROVIDERS_CHECK_FAIL',
                'description' => 'One or more providers missing',
                'meta' => ['missing' => $missing],
            ];
            $emit && $emit($fail);
            $this->log->appendInstallerEmit($fail);
            return ['status' => 'fail', 'missing' => $missing];
        }

        $ok = [
            'title' => 'PROVIDERS_CHECK_OK',
            'description' => 'All providers present in staged plugin',
            'meta' => ['count' => count($providers)],
        ];
        $emit && $emit($ok);
        $this->log->appendInstallerEmit($ok);

        return ['status' => 'ok'];
    }
}