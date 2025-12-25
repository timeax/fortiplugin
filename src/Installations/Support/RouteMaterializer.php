<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Support;

use RuntimeException;

final readonly class RouteMaterializer
{
    public function __construct(private AtomicFilesystem $afs) {}

    /**
     * @param string $pluginRoot
     * @param string $pluginSlug used in health route name & path
     * @param list<array{route:string|array, id:string, content:string, file:string}> $entries
     * @return array{dir:string, files:string[], aggregator:string}
     */
    public function materialize(string $pluginRoot, string $pluginSlug, array $entries): array
    {
        $routesDir = rtrim($pluginRoot, "\\/") . DIRECTORY_SEPARATOR . 'routes';
        if (!$this->afs->fs()->isDirectory($routesDir)) {
            $this->afs->fs()->ensureDirectory($routesDir, 0775);
        }

        $written = [];
        foreach ($entries as $e) {
            $rel  = ltrim((string)$e['file'], '/\\');
            $path = $routesDir . DIRECTORY_SEPARATOR . $rel;
            $this->afs->ensureParentDirectory($path);
            $this->afs->fs()->writeAtomic($path, (string)$e['content']);
            $written[] = $rel;
        }

        $aggregator = $this->writeAggregator($routesDir, $pluginSlug, $written);

        return ['dir' => $routesDir, 'files' => $written, 'aggregator' => $aggregator];
    }

    private function writeAggregator(string $routesDir, string $slug, array $files): string
    {
        if ($routesDir === '' || !$this->afs->fs()->isDirectory($routesDir)) {
            throw new RuntimeException("Invalid routes dir: $routesDir");
        }

        $target = rtrim($routesDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'fortiplugin.route.php';

        $lines = [];
        $lines[] = "<?php";
        $lines[] = "declare(strict_types=1);";
        $lines[] = "/** AUTO-GENERATED FortiPlugin aggregator for plugin: {$slug} */";
        $lines[] = "use Illuminate\\Support\\Facades\\Route;";
        $lines[] = "";
        // health endpoint
        $path = '/__plugins/' . $slug . '/health';
        $name = 'fortiplugin.' . $slug . '.health';
        $lines[] = "Route::get(" . var_export($path, true) . ", function () { return response('ok', 200); })->name(" . var_export($name, true) . ");";
        $lines[] = "";
        // includes
        foreach ($files as $rel) {
            $lines[] = "require __DIR__ . '/' . " . var_export($rel, true) . ";";
        }
        $lines[] = "";

        $this->afs->fs()->writeAtomic($target, implode("\n", $lines));

        return $target;
    }
}