<?php

namespace Timeax\FortiPlugin\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use SodiumException;
use stdClass;
use Symfony\Component\Process\Process;
use Throwable;
use JsonException;
use Timeax\FortiPlugin\Support\CliSessionManager;
use Timeax\FortiPlugin\Support\Encryption;

class MakePlugin extends Command
{
    protected $signature = 'forti:make
        {name : StudlyCase plugin name}
        {--force : Overwrite if plugin folder exists}
        {--view  : Scaffold TypeScript/Vite assets}
        {--no-npm : Skip npm install (CI / offline)}';

    protected $description = 'Scaffold a new plugin folder under the FortiPlugin directory';

    public function __construct(protected Filesystem $files)
    {
        parent::__construct();
    }

    /**
     * @throws JsonException
     * @throws SodiumException
     */
    public function handle(): int
    {
        // 1) Ensure session (host + bearer token)
        $session = CliSessionManager::getCurrentSession();
        if (!$session || empty($session['host']) || empty($session['token'])) {
            $this->error('You are not logged in. Run: forti:login');
            return self::FAILURE;
        }

        // 2) Normalize names
        $studly = Str::studly($this->argument('name'));
        $kebab = Str::kebab($studly);
        if (!preg_match('/^[a-z0-9\-_]{3,40}$/', $kebab)) {
            $this->error("Plugin alias must be 3–40 chars, lowercase a–z, 0–9, dash or underscore.");
            return self::FAILURE;
        }

        // 3) Handshake (host returns signature we’ll embed in .internal/Config.php)
        try {
            $hs = $this->api($session)->get('/forti/handshake')->json();
            if (!is_array($hs) || empty($hs['ok'])) {
                $this->warn('Handshake response not OK; continuing anyway.');
            }
            // Flexible extraction: accept several shapes
            $sigBlock =
                $hs['signature_block'] ??
                ($hs['signature']['block'] ?? null) ??
                ($hs['signature'] ?? null);

            if (!is_string($sigBlock) || trim($sigBlock) === '') {
                $this->error('Handshake did not include a signature block. Ensure the host adds `signature_block` or `signature.block` to /forti/handshake.');
                return self::FAILURE;
            }
        } catch (Throwable $e) {
            $this->error('Handshake failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        // 4) Init (create placeholder + issue placeholder token)
        try {
            $init = $this->api($session)->post('/forti/handshake/init', [
                'slug' => $kebab,
                'name' => $studly,
            ])->json();
        } catch (Throwable $e) {
            $this->error('Init failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        $placeholder = $init['placeholder'] ?? null;
        $pluginKey = $placeholder['key'] ?? null;   // unique_key
        $plToken = $init['token'] ?? null;        // raw once, keep safe
        $plExpires = $init['expires_at'] ?? null;

        if (!$pluginKey) {
            $this->error('Host did not return a plugin key (placeholder.unique_key).');
            return self::FAILURE;
        }

        // 5) Author info (still captured; signature comes from host)
        $author = [
            'name' => $session['name'] ?? $this->ask('Author name'),
            'email' => $session['email'] ?? $this->ask('Author email'),
        ];

        // 6) Local scaffold
        $baseDir = rtrim(config('fortiplugin.directory', base_path('Plugins')), DIRECTORY_SEPARATOR);
        $path = $baseDir . DIRECTORY_SEPARATOR . $studly;

        if ($this->files->exists($path) && !$this->option('force')) {
            $this->error("Folder exists: $path (use --force to overwrite).");
            return self::FAILURE;
        }
        $this->files->deleteDirectory($path);
        $this->files->makeDirectory($path, 0755, true);

        // plugin.config.json
        $this->files->put(
            "$path/fortiplugin.json",
            json_encode($this->defaultJson($studly, $kebab), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        // .internal
        $internalDir = "$path/.internal";
        $this->files->ensureDirectoryExists($internalDir);

        // .internal/Config.php — embed the HOST-PROVIDED signature block
        $this->files->put(
            "$internalDir/Config.php",
            $this->renderStubSafe('dev-config.php', [
                'PLUGIN_STUDLY' => $studly,
                'PLUGIN_ALIAS' => $kebab,
                'SIGNATURE_BLOCK' => $sigBlock, // ← from /forti/handshake
            ])
        );

        // Store placeholder token locally (do NOT add to VCS)
        if ($plToken) {
            $this->files->put(
                "$internalDir/placeholder.token.json",
                json_encode([
                    'placeholder_slug' => $kebab,
                    'plugin_key' => $pluginKey,
                    'token' => Encryption::encrypt($plToken),
                    'expires_at' => $plExpires,
                    'host' => rtrim($session['host'], '/'),
                ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );
            $this->warn("Saved placeholder token to .internal/placeholder.token.json (ensure it’s gitignored).");
        }

        // Core dirs
        $src = "$path/src";
        foreach ([
                     "$src/Providers",
                     "$src/Models",
                     "$src/Support",
                     "$src/Http/Controllers",
                     "$src/Http/Middleware",
                     "$src/Http/Routes",
                     "$path/database/migrations",
                     "$path/database/factories",
                     "$path/routes",
                     "$path/config",
                     "$path/public",
                     "$path/resources/shared/ts",
                 ] as $dir) {
            $this->files->ensureDirectoryExists($dir);
        }

        // Optional assets
        if ($this->option('view')) {
            $this->scaffoldViewAssets($path);
            if (!$this->option('no-npm')) {
                $this->runNpmInstall($path);
                $this->runTailwindInit($path);
            }
        }

        // composer dump-autoload (best effort)
        if ($this->files->exists(base_path('composer.json'))) {
            $this->line('> composer dump-autoload');
            (new Process(['composer', 'dump-autoload']))->run(fn($t, $b) => $this->output->write($b));
        }

        // publish.json (safe to commit; does NOT include token)
        $publishPath = "$path/publish.json";
        if ($this->files->exists($publishPath) && !$this->confirm("publish.json exists. Overwrite?")) {
            $this->warn("Skipped publish.json.");
            $this->info("Plugin '$studly' scaffolded.");
            return self::SUCCESS;
        }

        $this->files->put($publishPath, json_encode([
            'host' => rtrim($session['host'], '/'),
            'plugin_slug' => $kebab,
            'plugin_key' => $pluginKey,
            'author' => $author,
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->info("publish.json generated.");
        $this->info("Plugin '$studly' scaffolded.");
        $this->line("Next: php artisan forti:install $studly");

        return self::SUCCESS;
    }

    /* ───────────────── helpers ───────────────── */

    protected function defaultJson(string $studly, string $kebab): array
    {
        return [
            'name' => $studly,
            'alias' => $kebab,
            'description' => '',
            'version' => '0.1.0',
            'providers' => [],
            'dependencies' => [],
            'apiConfig' => [],
            'uiConfig' => ['routes' => [], 'extend' => new stdClass()],
            'routes' => [],
            'db_permissions' => new stdClass(),
            'fileAccess' => [],
            'exports' => [],
        ];
    }

    protected function scaffoldViewAssets(string $pluginPath): void
    {
        $this->files->ensureDirectoryExists("$pluginPath/resources/inertia/ts/Pages");
        $this->files->put("$pluginPath/resources/inertia/ts/app.tsx", <<<TS
import React from 'react';
import { createInertiaApp } from '@inertiajs/react';

createInertiaApp({
  resolve: (name) => import(\`./Pages/\${name}.tsx\`),
  setup({ el, App, props }) {
    return <App {...props} />;
  },
});
TS
        );
        $this->files->put(
            "$pluginPath/resources/inertia/ts/Pages/Welcome.tsx",
            "export default () => <h1 className='text-2xl font-bold'>Welcome from {$this->argument('name')}</h1>;"
        );

        $this->files->ensureDirectoryExists("$pluginPath/resources/embed/ts/pages");
        $this->files->ensureDirectoryExists("$pluginPath/resources/embed/ts/addons");
        $this->files->put("$pluginPath/resources/embed/ts/Hello.tsx", "export default () => <div className='p-2 bg-indigo-100'>Embedded Hello!</div>;");

        $this->files->put("$pluginPath/resources/embed/vite.input.js", $this->renderStubSafe("viteInputGen"));
        $this->files->put("$pluginPath/vite.config.js", $this->renderStubSafe("viteConfig"));
        $this->files->put("$pluginPath/tsconfig.json", $this->renderStubSafe("tsconfig"));

        $this->files->put("$pluginPath/package.json", <<<JSON
{
  "name": "{$this->argument('name')}",
  "private": true,
  "type": "module",
  "scripts": {
    "dev": "vite dev",
    "build": "vite build",
    "type-check": "tsc --noEmit"
  }
}
JSON
        );
    }

    protected function runNpmInstall(string $cwd): void
    {
        $this->info('Running npm install…');
        $cmd = ['npm', 'install', '-D', 'vite', 'typescript', '@vitejs/plugin-react', 'tyger-plugin-prep', '@types/react', '@types/react-dom', 'tailwindcss', 'postcss', 'autoprefixer'];
        (new Process($cmd, $cwd))->setTimeout(600)->run(fn($t, $b) => $this->output->write($b));
    }

    protected function runTailwindInit(string $cwd): void
    {
        $this->line('Initializing Tailwind config…');
        (new Process(['npx', 'tailwindcss', 'init', '-p'], $cwd))->run();
    }

    /** Simple, safe token substitution for stubs: replaces #{UPPER_VARS} only. */
    protected function renderStubSafe(string $name, array $vars = []): string
    {
        $stubDir = dirname(__DIR__, 2) . '/stub'; // adjust if needed
        $path = str_ends_with($name, '.stub') ? "$stubDir/$name" : "$stubDir/$name.stub";
        if (!file_exists($path)) throw new RuntimeException("Stub not found: $path");

        $contents = file_get_contents($path);
        return preg_replace_callback('/#\{([A-Z0-9_]+)}/', static function ($m) use ($vars) {
            $key = $m[1] ?? '';
            return isset($vars[$key]) ? (string)$vars[$key] : $m[0];
        }, $contents);
    }

    /** Preconfigured HTTP client to the host. */
    protected function api(array $session): PendingRequest|Factory
    {
        $host = rtrim($session['host'], '/');
        $token = $session['token'];

        return Http::withToken($token)
            ->acceptJson()
            ->baseUrl($host);
    }
}