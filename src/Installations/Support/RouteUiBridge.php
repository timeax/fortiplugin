<?php /** @noinspection GrazieInspection */
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Support;

use FilesystemIterator;
use JsonException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Timeax\FortiPlugin\Core\Install\JsonRouteCompiler;

/**
 * RouteUiBridge
 *
 * - Reads routes config from <staging>/fortiplugin.json
 * - Discovers JSON route files using routes.dir + routes.glob (supports **)
 * - Compiles via JsonRouteCompiler:
 *     • legacy "compiled" chunks (compat)
 *     • registry-first entries {route,id,content,file}
 * - Persists registry to <staging>/.internal/routes.registry.json
 *
 * Output shape:
 *   [
 *     'compiled'   => array<int, array{source:string,php:string,routeIds:string[],slug:string}>,
 *     'registry'   => list<array{route:string|array, id:string, content:string, file:string}>,
 *     'route_ids'  => string[],
 *     'files'      => string[],   // discovered JSON files (absolute)
 *     'root'       => string,     // absolute: <staging>/<routes.dir>
 *     'pattern'    => string,     // glob pattern used
 *   ]
 */
final readonly class RouteUiBridge
{
    public function __construct(
        private AtomicFilesystem  $afs,
        private JsonRouteCompiler $compiler,
        private RouteRegistryStore $registryStore,
    ) {}

    /**
     * Discover and compile all route JSON files for a staged plugin.
     *
     * @param string        $stagingRoot Absolute path to staged plugin root (directory containing fortiplugin.json)
     * @param callable|null $emit        Optional emitter: fn(array $payload): void
     * @return array{compiled:array,registry:array,route_ids:array,files:array,root:string,pattern:string}
     * @throws JsonException
     */
    public function discoverAndCompile(string $stagingRoot, ?callable $emit = null): array
    {
        $stagingRoot = rtrim($stagingRoot, "\\/");

        $cfgPath = $stagingRoot . DIRECTORY_SEPARATOR . 'fortiplugin.json';
        $fs = $this->afs->fs();

        if (!$fs->exists($cfgPath) || !$fs->isFile($cfgPath)) {
            throw new RuntimeException("fortiplugin.json not found at $cfgPath");
        }

        $cfg = $fs->readJson($cfgPath);
        $routesCfg = (array)($cfg['routes'] ?? []);
        $dirRel = (string)($routesCfg['dir'] ?? '');
        if ($dirRel === '') {
            throw new RuntimeException("fortiplugin.json: routes.dir is required");
        }

        $glob = (string)($routesCfg['glob'] ?? '**/*.routes.json');
        $root = $stagingRoot . DIRECTORY_SEPARATOR . ltrim(str_replace(['\\'], '/', $dirRel), '/');

        if (!$fs->exists($root) || !$fs->isDirectory($root)) {
            throw new RuntimeException("Routes directory not found: $root");
        }

        $emit && $emit([
            'title' => 'ROUTE_DISCOVERY_START',
            'description' => 'Searching for JSON route files',
            'meta' => ['root' => $root, 'pattern' => $glob],
        ]);

        $files = $this->findRouteFiles($root, $glob);

        $emit && $emit([
            'title' => 'ROUTE_DISCOVERY_DONE',
            'description' => 'Route files discovered',
            'meta' => ['count' => count($files), 'root' => $root],
        ]);

        if ($files === []) {
            return [
                'compiled'  => [],
                'registry'  => [],
                'route_ids' => [],
                'files'     => [],
                'root'      => $root,
                'pattern'   => $glob,
            ];
        }

        $emit && $emit([
            'title' => 'ROUTE_COMPILE_START',
            'description' => 'Compiling route JSON files',
            'meta' => ['count' => count($files)],
        ]);

        // Legacy output for compatibility with existing callers:
        $compiled = $this->compiler->compileFiles($files);

        // Registry-first entries (authoritative per-route units):
        $registryEntries = [];
        $seenIds = [];
        foreach ($files as $file) {
            $r = $this->compiler->compileFileToRegistry($file);
            foreach ($r['entries'] as $entry) {
                $registryEntries[] = $entry;
            }
            foreach ($r['routeIds'] as $rid) {
                $seenIds[(string)$rid] = true;
            }
        }
        $routeIds = array_keys($seenIds);
        sort($routeIds, SORT_STRING);

        // Persist registry to .internal
        $this->registryStore->write($stagingRoot, $registryEntries);

        $emit && $emit([
            'title' => 'ROUTE_COMPILE_DONE',
            'description' => 'Routes compiled',
            'meta' => ['compiled' => count($compiled), 'registry_entries' => count($registryEntries), 'route_ids' => count($routeIds)],
        ]);

        return [
            'compiled'  => $compiled,
            'registry'  => $registryEntries,
            'route_ids' => $routeIds,
            'files'     => $files,
            'root'      => $root,
            'pattern'   => $glob,
        ];
    }

    /**
     * Find route files within $root using a glob-like $pattern (supports **).
     * Returns absolute paths.
     */
    private function findRouteFiles(string $root, string $pattern): array
    {
        $root = rtrim($root, DIRECTORY_SEPARATOR);
        $normPattern = $this->normalizePattern($pattern);

        $out = [];
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS)
        );

        foreach ($it as $file) {
            /** @var SplFileInfo $file */
            if (!$file->isFile()) continue;

            $abs = $file->getPathname();
            $rel = str_replace('\\', '/', substr($abs, strlen($root) + 1));
            if ($this->globMatch($rel, $normPattern)) {
                $out[] = $abs;
            }
        }

        sort($out, SORT_STRING);
        return $out;
    }

    private function normalizePattern(string $pattern): string
    {
        $p = str_replace('\\', '/', $pattern);
        return $p !== '' ? $p : '**/*.routes.json';
    }

    private function globMatch(string $relForwardSlash, string $pattern): bool
    {
        $quoted = preg_quote($pattern, '~');
        $quoted = str_replace(['\*\*', '\*', '\?'], ['.*', '[^/]*', '[^/]'], $quoted);
        $re = '~^' . $quoted . '$~u';
        return (bool)preg_match($re, $relForwardSlash);
    }
}