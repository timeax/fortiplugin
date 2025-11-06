<?php

namespace Timeax\FortiPlugin\Core\Install;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Random\RandomException;
use RuntimeException;
use Timeax\FortiPlugin\Models\Plugin;
use Timeax\FortiPlugin\Models\PluginRoutePermission;
use Timeax\FortiPlugin\Exceptions\MissingRoutePermissionsException;

final class RouteWriter
{
    public function __construct(
        private readonly Filesystem $fs,
        private ?string             $outputRoot = null,
    )
    {
    }

    public function setOutputRoot(string $root): self
    {
        $this->outputRoot = $root;
        return $this;
    }

    /**
     * @param Plugin $plugin
     * @param array<int, array{
     *   source?: string,     // original JSON path (optional)
     *   php: string,         // compiled Route::* statements
     *   routeIds: string[],  // route ids contained in this chunk (for permission checks)
     *   slug: string         // snake-case slug from the JSON compiler (unique per file)
     * }> $compiled
     * @return array{dir:string, files:string[], index:string}
     * @throws RandomException
     */
    public function write(Plugin $plugin, array $compiled): array
    {
        // 1) Enforce per-route approvals
        $allIds = $this->collectRouteIds($compiled);
        $missing = $this->missingApprovals($plugin->id, $allIds);
        if (!empty($missing)) {
            throw new MissingRoutePermissionsException($missing, $plugin->placeholder->slug);
        }

        // 2) Resolve target dir: {output_root}/{plugin-slug}
        $root = $this->resolveOutputRoot();
        $pluginSlug = $plugin->placeholder->slug;
        $targetDir = $this->safeJoin($root, $pluginSlug);
        $this->ensureDir($targetDir);

        // 3) Write one file per JSON definition (name = compiler slug)
        $filesWritten = [];
        $header = (string)config('fortiplugin.routes.header', '');
        $pattern = (string)config('fortiplugin.routes.split_pattern', '{file_slug}.forti.php');

        foreach ($compiled as $item) {
            $fileSlug = (string)$item['slug']; // guaranteed by compiler
            $name = $this->interpolate($pattern, [
                'file_slug' => $fileSlug,
                'slug' => $pluginSlug, // in case host's pattern wants plugin slug too
            ]);

            $path = $this->safeJoin($targetDir, $name);
            $contents = $this->wrap($header, (string)$item['php']);
            $this->atomicWrite($path, $contents);
            $filesWritten[] = $path;
        }

        // 4) Generate plugin index.php that requires each file (sorted, stable order)
        usort($filesWritten, static fn($a, $b) => strcmp(basename($a), basename($b)));
        $indexPath = $this->writeIndex($targetDir, $filesWritten, $pluginSlug);

        return ['dir' => $targetDir, 'files' => $filesWritten, 'index' => $indexPath];
    }

    /** @param array<int, array{routeIds?: string[]}> $compiled */
    private function collectRouteIds(array $compiled): array
    {
        $seen = [];
        foreach ($compiled as $chunk) {
            if (!empty($chunk['routeIds']) && is_array($chunk['routeIds'])) {
                foreach ($chunk['routeIds'] as $id) {
                    $id = (string)$id;
                    if ($id !== '') $seen[$id] = true;
                }
            }
        }
        $out = array_keys($seen);
        sort($out);
        return $out;
    }

    /** @param string[] $ids */
    private function missingApprovals(int $pluginId, array $ids): array
    {
        if (empty($ids)) return [];

        $approved = PluginRoutePermission::query()
            ->where('plugin_id', $pluginId)
            ->where('status', 'approved')
            ->whereIn('route_id', $ids)
            ->pluck('route_id')
            ->all();

        $approved = array_map('strval', $approved);
        return array_values(array_diff($ids, $approved));
    }

    private function resolveOutputRoot(): string
    {
        $root = $this->outputRoot ?? (string)config('fortiplugin.routes.output_root', base_path('routes'));
        return rtrim($root, DIRECTORY_SEPARATOR);
    }

    private function ensureDir(string $path): void
    {
        if (!$this->fs->exists($path)) {
            $this->fs->makeDirectory($path, 0755, true);
        }
        if (!is_dir($path) || !is_writable($path)) {
            throw new RuntimeException("RouteWriter cannot write to directory: $path");
        }
    }

    private function safeJoin(string $base, string $child): string
    {
        $child = ltrim($child, '/\\');
        $full = $base . DIRECTORY_SEPARATOR . $child;
        $realBase = realpath($base) ?: $base;
        $realFull = $this->realpathSafe($full);
        if (!Str::startsWith($realFull, rtrim($realBase, DIRECTORY_SEPARATOR))) {
            throw new RuntimeException("Refusing to write outside allowed root: $realFull");
        }
        return $realFull;
    }

    private function realpathSafe(string $path): string
    {
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $dir));
        }
        $rp = realpath($dir) ?: $dir;
        return $rp . DIRECTORY_SEPARATOR . basename($path);
    }

    private function wrap(string $header, string $code): string
    {
        $code = ltrim($code);
        if ($header !== '') {
            return rtrim($header, "\r\n") . "\n\n" . preg_replace('/^\s*<\?php\b/', '', $code);
        }
        return Str::startsWith($code, '<?php') ? $code : "<?php\n" . $code;
    }

    /**
     * @throws RandomException
     */
    private function atomicWrite(string $path, string $contents): void
    {
        $tmp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
        $bytes = @file_put_contents($tmp, $contents, LOCK_EX);
        if ($bytes === false) {
            @unlink($tmp);
            throw new RuntimeException("Failed writing temp file: $tmp");
        }
        if (!@rename($tmp, $path)) {
            @unlink($tmp);
            throw new RuntimeException("Failed to move temp file into place: $path");
        }
    }

    private function interpolate(string $pattern, array $vars): string
    {
        return preg_replace_callback('/\{([a-z_]+)}/i', static fn($m) => $vars[$m[1]] ?? $m[0], $pattern);
    }

    /**
     * Create (or replace) routes/{slug}/index.php that requires each generated file.
     * @throws RandomException
     */
    private function writeIndex(string $targetDir, array $filesWritten, string $slug): string
    {
        $indexPath = $this->safeJoin($targetDir, 'index.php');

        $lines = [];
        $lines[] = "<?php";
        $lines[] = "/**";
        $lines[] = " * AUTO-GENERATED by FortiPlugin.";
        $lines[] = " * Plugin: $slug";
        $lines[] = " */";
        $lines[] = "declare(strict_types=1);";
        $lines[] = "";

        foreach ($filesWritten as $abs) {
            $fname = basename($abs);
            // require files from same dir
            $lines[] = "require __DIR__ . '/$fname';";
        }
        $lines[] = "";

        $this->atomicWrite($indexPath, implode("\n", $lines));
        return $indexPath;
    }
}