<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Sections;

use JsonException;
use RuntimeException;
use Throwable;
use Timeax\FortiPlugin\Installations\Support\AtomicFilesystem;
use Timeax\FortiPlugin\Installations\Support\InstallationLogStore;
use Timeax\FortiPlugin\Installations\Support\RouteMaterializer;
use Timeax\FortiPlugin\Installations\Support\RouteRegistryStore;
use Timeax\FortiPlugin\Models\Plugin;

/**
 * RouteWriteSection (registry-first)
 *
 * - Reads registry (.internal/routes.registry.json) written by RouteUiBridge.
 * - Materializes per-route PHP files into <staging>/routes/.
 * - Writes aggregator "fortiplugin.route.php" with a health route and requires.
 * - Persists a "routes_write" block (dir, files, aggregator, registry).
 * - Emits start/ok/fail installer events.
 */
final readonly class RouteWriteSection
{
    public function __construct(
        private InstallationLogStore $log,
        private AtomicFilesystem     $afs,
        private RouteRegistryStore   $registry,
        private RouteMaterializer    $materializer,
    )
    {
    }

    /**
     * @param Plugin $plugin Eloquent Plugin model (slug used for health route)
     * @param array<int, array{
     *   source?: string,
     *   php: string,
     *   routeIds: string[],
     *   slug: string
     * }> $compiled (ignored for writing; kept for compatibility with caller)
     * @param callable|null $emit Optional installer emitter fn(array $payload): void
     *
     * @return array{
     *   status: 'ok'|'fail',
     *   dir?: string,
     *   files?: string[],
     *   aggregator?: string,
     *   registry?: string,
     *   reason?: string
     * }
     *
     * @throws JsonException
     */
    public function run(
        Plugin    $plugin,
        array     $compiled,
        ?callable $emit = null
    ): array
    {
        // Resolve STAGING root from installation log meta
        $doc = $this->log->read();
        $meta = (array)($doc['meta'] ?? []);
        $paths = (array)($meta['paths'] ?? []);
        $stagingRoot = (string)($paths['staging'] ?? '');

        if ($stagingRoot === '') {
            throw new RuntimeException('RouteWriteSection: missing meta.paths.staging in InstallationLogStore.');
        }

        $emit && $emit([
            'title' => 'ROUTES_WRITE_START',
            'description' => 'Materializing routes from registry',
            'meta' => ['staging_root' => $stagingRoot, 'chunks_seen' => count($compiled)],
        ]);
        $this->log->appendInstallerEmit([
            'title' => 'ROUTES_WRITE_START',
            'description' => 'Materializing routes from registry',
            'meta' => ['staging_root' => $stagingRoot],
        ]);

        try {
            $entries = $this->registry->read($stagingRoot);
            if ($entries === []) {
                // Nothing to write (okay)
                $doc = [
                    'dir' => rtrim($stagingRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'routes',
                    'files' => [],
                    'aggregator' => null,
                    'registry' => $this->registry->path($stagingRoot),
                ];
                $this->log->writeSection('routes_write', $doc);

                $emit && $emit([
                    'title' => 'ROUTES_WRITE_OK',
                    'description' => 'No registry entries to write',
                    'meta' => ['dir' => $doc['dir'], 'file_count' => 0],
                ]);
                $this->log->appendInstallerEmit([
                    'title' => 'ROUTES_WRITE_OK',
                    'description' => 'No registry entries to write',
                    'meta' => ['dir' => $doc['dir'], 'file_count' => 0],
                ]);

                return ['status' => 'ok'] + $doc;
            }

            $slug = (string)($plugin->placeholder->slug ?? $plugin->slug ?? 'plugin');
            $mat = $this->materializer->materialize($stagingRoot, $slug, $entries);

            $out = [
                'dir' => $mat['dir'],
                'files' => $mat['files'],
                'aggregator' => $mat['aggregator'],
                'registry' => $this->registry->path($stagingRoot),
            ];

            $this->log->writeSection('routes_write', $out);

            $emit && $emit([
                'title' => 'ROUTES_WRITE_OK',
                'description' => 'Routes registry materialized',
                'meta' => ['dir' => $mat['dir'], 'file_count' => count($mat['files']), 'aggregator' => $mat['aggregator']],
            ]);
            $this->log->appendInstallerEmit([
                'title' => 'ROUTES_WRITE_OK',
                'description' => 'Routes registry materialized',
                'meta' => ['dir' => $mat['dir'], 'file_count' => count($mat['files']), 'aggregator' => $mat['aggregator']],
            ]);

            return ['status' => 'ok'] + $out;
        } catch (Throwable $e) {
            $emit && $emit([
                'title' => 'ROUTES_WRITE_FAIL',
                'description' => 'Materialization error',
                'meta' => ['exception' => $e->getMessage()],
            ]);
            $this->log->appendInstallerEmit([
                'title' => 'ROUTES_WRITE_FAIL',
                'description' => 'Materialization error',
                'meta' => ['exception' => $e->getMessage()],
            ]);

            $this->log->writeSection('routes_write', [
                'error' => 'exception',
                'exception' => $e->getMessage(),
                'registry' => $this->registry->path($stagingRoot),
            ]);

            return ['status' => 'fail', 'reason' => 'exception'];
        }
    }
}