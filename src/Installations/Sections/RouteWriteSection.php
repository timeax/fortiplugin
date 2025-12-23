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
     * @param callable $emit Installer emitter fn(array $payload): void (non-null; persistence handled upstream)
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
        callable  $emit
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

        $start = [
            'title' => 'ROUTES_WRITE_START',
            'description' => 'Materializing routes from registry',
            'meta' => ['staging_root' => $stagingRoot, 'chunks_seen' => count($compiled)],
        ];
        $emit($start);

        $registryRel = '.internal' . DIRECTORY_SEPARATOR . 'routes.registry.json';

        try {
            $entries = $this->registry->read($stagingRoot);
            if ($entries === []) {
                // Nothing to write (okay)
                $doc = [
                    'dir' => 'routes',
                    'files' => [],
                    'aggregator' => null,
                    'registry' => $registryRel,
                ];
                $this->log->writeSection('routes_write', $doc);

                $okEmpty = [
                    'title' => 'ROUTES_WRITE_OK',
                    'description' => 'No registry entries to write',
                    'meta' => ['dir' => $doc['dir'], 'file_count' => 0],
                ];
                $emit($okEmpty);

                return ['status' => 'ok'] + $doc;
            }

            $slug = (string)($plugin->placeholder->slug ?? $plugin->slug ?? 'plugin');
            $mat = $this->materializer->materialize($stagingRoot, $slug, $entries);

            $out = [
                'dir' => $mat['dir'],
                'files' => $mat['files'],
                'aggregator' => $mat['aggregator'],
                'registry' => $registryRel,
            ];

            $this->log->writeSection('routes_write', $out);

            $ok = [
                'title' => 'ROUTES_WRITE_OK',
                'description' => 'Routes registry materialized',
                'meta' => ['dir' => $mat['dir'], 'file_count' => count($mat['files']), 'aggregator' => $mat['aggregator']],
            ];
            $emit($ok);

            return ['status' => 'ok'] + $out;
        } catch (Throwable $e) {
            $fail = [
                'title' => 'ROUTES_WRITE_FAIL',
                'description' => 'Materialization error',
                'meta' => ['exception' => $e->getMessage()],
            ];
            $emit($fail);

            $this->log->writeSection('routes_write', [
                'error' => 'exception',
                'exception' => $e->getMessage(),
                'registry' => $registryRel,
            ]);

            return ['status' => 'fail', 'reason' => 'exception'];
        }
    }
}