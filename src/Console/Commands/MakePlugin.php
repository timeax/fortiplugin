<?php /** @noinspection HtmlUnknownAttribute */

/** @noinspection NpmUsedModulesInstalled */

namespace Timeax\FortiPlugin\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use JsonException;
use stdClass;
use Symfony\Component\Process\Process;
use Throwable;
use Timeax\FortiPlugin\Traits\PluginHandshake;

class MakePlugin extends Command
{
    use PluginHandshake;

    protected $signature = 'fp:make
        {name : StudlyCase plugin name}
        {alias : StudlyCase plugin alias}
        {--force : Overwrite if plugin folder exists}
        {--view  : Scaffold TypeScript Inertia + Embed resource folders}
        {--no-npm : Skip npm install (CI / offline)}';

    protected $description = 'Scaffold a new plugin folder under the Plugins directory';

    public function __construct(protected Filesystem $files)
    {
        parent::__construct();
    }

    /**
     * @throws Throwable
     * @throws JsonException
     */
    public function handle(): int
    {
        // 1) Ensure session (host + bearer token)
        $session = $this->auth();
        if (!$session) return self::FAILURE;

        // 2) Validate plugin name/alias
        $studly = Str::studly($this->argument('alias'));
        $kebab = Str::kebab($studly);

        if (!preg_match('/^[a-z0-9\-_]{3,40}$/', $kebab)) {
            $this->error("Plugin alias must be 3-40 characters, lowercase a-z, 0-9, dash or underscore only.");
            return self::FAILURE;
        }

        // 3) Prepare the local path
        $base = config('fortiplugin.dev_directory', 'Plugins');
        $path = $base . DIRECTORY_SEPARATOR . $studly;

        if ($this->files->exists($path) && !$this->option('force')) {
            $this->error("Plugin '$studly' already exists locally (use --force to overwrite).");
            return self::FAILURE;
        }
        $this->files->deleteDirectory($path);
        $this->files->makeDirectory($path, 0755, true);

        // 4) Perform Handshake and Init Placeholder
        $handshakeResult = $this->performHandshakeAndInit($this, $this->files, $path, $studly, $kebab, $session);
        if (!$handshakeResult) {
            return self::FAILURE;
        }

        $pluginKey = $handshakeResult['pluginKey'];
        $author = $handshakeResult['author'];
        $psr4Root = $handshakeResult['psr4Root'];

        // 5) Write fortiplugin.json
        $this->files->put(
            "$path/fortiplugin.json",
            json_encode($this->defaultJson($studly, $kebab), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        // 6) composer.json via stub
        $this->files->put(
            "$path/composer.json",
            $this->renderStub('composer', [
                'PLUGIN_SLUG' => $kebab,
                'AUTHOR_NAME' => $author['name'] ?? '',
                'AUTHOR_EMAIL' => $author['email'] ?? '',
                'PLUGIN_ROOT_FOLDER' => $psr4Root,
                'PLUGIN_NAME' => $studly,
            ])
        );

        // 7) Create directories
        $src = "$path/src";
        foreach (
            [
                "$src/Providers",
                "$src/Models",
                "$src/Support",
                "$src/Http/Controllers",
                "$src/Http/Middleware",
                "$path/database/migrations",
                "$path/database/factories",
                "$path/routes",
                "$path/config",
                "$path/public",
                "$path/resources/shared",
            ] as $dir
        ) {
            $this->files->ensureDirectoryExists($dir);
        }

        // 8) Create default permissions.json
        $this->files->put(
            "$path/permissions.json",
            json_encode([], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        // 9) Optional TS/Vite scaffold
        if ($this->option('view')) {
            $this->scaffoldViewAssets($path);
            if (!$this->option('no-npm')) {
                $this->runNpmInstall($path);
                $this->runTailwindInit($path);
            }
        }

        // 10) composer dump-autoload (host project)
        if ($this->files->exists(base_path('composer.json'))) {
            $this->line('> composer dump-autoload');
            (new Process(['composer', 'dump-autoload']))->run(fn($t, $b) => $this->output->write($b));
        }

        // 11) publish.json
        $publishPath = $path . "/publish.json";
        if ($this->files->exists($publishPath) && $this->confirm("publish.json already exists. Overwrite?")) {
            $this->info("Skipping publish.json overwrite.");
            return self::SUCCESS;
        }

        $this->files->put($publishPath, json_encode([
            'host' => $session['host'],
            'plugin_slug' => $kebab,
            'plugin_key' => $pluginKey,
            'author' => $author,
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->info("Plugin '$studly' scaffolded.");
        return self::SUCCESS;
    }

    /* ───────────────────────── Helpers ───────────────────────── */

    protected function defaultJson(string $studly, string $kebab): array
    {
        return [
            '$schema' => 'https://raw.githubusercontent.com/timeax/fortiplugin/refs/heads/main/schema/fortiplugin.schema.json',
            'name' => $studly,
            'alias' => $kebab,
            'description' => '',
            'version' => '0.1.0',
            'providers' => [],

            // Map<string, DependencySpec> → must be an object
            'dependencies' => new stdClass(),

            // Array<HostConfig>
            'hostConfig' => [],

            'permission_manifest' => 'permissions.json',

            // { items: UiItem[] }
            'uiConfig' => [
                'items' => [],
            ],

            // { dir: string; glob?: string }
            'routes' => [
                'dir' => 'routes',
                'glob' => '*.routes.json',
            ],

            // Record<Slug, ExportDefinition> → must be an object
            'exports' => new stdClass(),
        ];
    }

    protected function scaffoldViewAssets(string $pluginPath): void
    {
        // Inertia entry + sample page
        $this->files->ensureDirectoryExists("$pluginPath/resources/inertia/Pages");
        $this->files->put(
            "$pluginPath/resources/inertia/app.tsx",
            <<<TS
import React from 'react';
import { createInertiaApp } from '@inertiajs/react';

createInertiaApp({
  resolve: (name) => import(`./Pages/\${name}.tsx`),
  setup({ el, App, props }) {
    return <App {...props} />;
  },
});
TS
        );
        $this->files->put(
            "$pluginPath/resources/inertia/Pages/Welcome.tsx",
            "export default () => <h1 className='text-2xl font-bold'>Welcome from {$this->argument('name')}</h1>;"
        );

        // Embed sample component
        $this->files->ensureDirectoryExists("$pluginPath/resources/embed/pages");
        $this->files->ensureDirectoryExists("$pluginPath/resources/embed/addons");
        $this->files->put(
            "$pluginPath/resources/embed/Hello.tsx",
            "export default () => <div className='p-2'>Embedded Hello!</div>;"
        );

        // vite.config.js
        $this->files->put(
            "$pluginPath/vite.config.spa.js",
            $this->renderStub("vite.config.spa.stub")
        );

        $this->files->put(
            "$pluginPath/vite.config.embed.js",
            $this->renderStub("vite.config.embed.stub")
        );

        // tsconfig.json
        $this->files->put(
            "$pluginPath/tsconfig.json",
            $this->renderStub("tsconfig")
        );

        // package.json (bare)
        $this->files->put(
            "$pluginPath/package.json",
            $this->renderStub("package.json", ["package_name" => $this->argument('name')])
        );
    }

    protected function runNpmInstall(string $cwd): void
    {
        $this->info('Running npm install…');
        $cmd = [
            'npm', 'install', '-D',
            'vite', 'typescript', '@vitejs/plugin-react',
            '@types/react', '@types/react-dom',
            'tailwindcss',
            'fortiplugin-bundle-adapter'
        ];
        (new Process($cmd, $cwd))->setTimeout(600)->run(fn($t, $b) => $this->output->write($b));
    }

    protected function runTailwindInit(string $cwd): void
    {
        $this->line('Initializing Tailwind config…');
        (new Process(['npx', 'tailwindcss', 'init', '-p'], $cwd))->run();
    }
}
