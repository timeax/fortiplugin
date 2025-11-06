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
use Timeax\FortiPlugin\Traits\AuthenticateSession;
use Timeax\FortiPlugin\Traits\Stubber;

class MakePlugin extends Command
{
    use AuthenticateSession, Stubber;

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

        $client = $this->getHttp();
        if (!$client) {
            $this->error('Could not create API client from your session.');
            return self::FAILURE;
        }
        $structure = $client->get('/forti/structure');
        // 3) Prepare the local path
        $base = $structure['directory'] ?? 'Plugins';
        $path = $base . DIRECTORY_SEPARATOR . $studly;

        if ($this->files->exists($path) && !$this->option('force')) {
            $this->error("Plugin '$studly' already exists locally (use --force to overwrite).");
            return self::FAILURE;
        }
        $this->files->deleteDirectory($path);
        $this->files->makeDirectory($path, 0755, true);

        // 4) Contact host: handshake (policy + verify + signature block)
        $handshake = $this->getJson($client->get('/forti/handshake'));
        if (!($handshake['ok'] ?? false)) {
            $this->error('Handshake failed.');
            return self::FAILURE;
        }

        $signatureBlock = $handshake['signature_block'] ?? null;
        if (!$signatureBlock) {
            $this->warn('Host did not return a signature_block. Continuing without it.');
        }

        // 5) Ask/derive author info (prefer from session)
        $author = [
            'name' => $session['name'] ?? $this->ask('Author name'),
            'email' => $session['email'] ?? $this->ask('Author email'),
        ];

        // 6) Init placeholder (+ placeholder token)
        $init = $this->getJson($client->post('/forti/handshake/init', [
            'slug' => $kebab,
            'name' => $studly,
        ]));

        if (!($init['ok'] ?? false)) {
            $this->error('Failed to create placeholder on host.');
            return self::FAILURE;
        }

        $placeholder = $init['placeholder'] ?? [];
        $placeholderId = $placeholder['id'] ?? null;
        $pluginKey = $placeholder['key'] ?? null;  // unique_key from server
        $phToken = $init['token'] ?? null;

        if (!$pluginKey) {
            $this->error('Host did not return a plugin key for the placeholder.');
            return self::FAILURE;
        }

        // 7) Write fortiplugin.json
        $this->files->put(
            "$path/fortiplugin.json",
            json_encode($this->defaultJson($studly, $kebab), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        // 7b) composer.json via stub
        $psr4Root = $init['psr4_root'] ?? 'Plugins'; // e.g. "Plugins"
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

        // 8) Write .internal files
        $internalDir = "$path/.internal";
        $this->files->makeDirectory($internalDir, 0755, true);

        // 8a) Host-provided signature block into Config.php (via stub or direct)
        // 8a) Host-provided signature block into Config.php via stub
        $this->files->put(
            "$internalDir/Config.php",
            $this->renderStub('config-dev', [
                'PLUGIN_STUDLY' => $studly,
                'PLUGIN_ALIAS' => $kebab,
                "PLUGIN_NAMESPACE" => $psr4Root,
                "PLUGIN_ID" => $init['placeholder']['id'] ?? 1,
                // host returns this:
                'SIGNATURE_BLOCK' => $signatureBlock ?? "// (signature block not returned by host)",
            ])
        );

        // 8b) Store placeholder token (single-use raw) — advise .gitignore
        if ($phToken) {
            $this->files->put(
                "$internalDir/placeholder.token.json",
                json_encode(['token' => $phToken, 'placeholder_id' => $placeholderId, 'slug' => $kebab], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT)
            );
        }

        // 9) Create directories
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
                "$path/public/index.php",
                "$path/resources/shared/ts",
            ] as $dir
        ) {
            $this->files->ensureDirectoryExists($dir);
        }

        // 10) Optional TS/Vite scaffold
        if ($this->option('view')) {
            $this->scaffoldViewAssets($path);
            if (!$this->option('no-npm')) {
                $this->runNpmInstall($path);
                $this->runTailwindInit($path);
            }
        }

        // 11) composer dump-autoload (host project)
        if ($this->files->exists(base_path('composer.json'))) {
            $this->line('> composer dump-autoload');
            (new Process(['composer', 'dump-autoload']))->run(fn($t, $b) => $this->output->write($b));
        }

        // 12) publish.json
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
            '$schema' => 'https://github.com/timeax/fortiplugin/blob/main/schema/fortiplugin.schema.json',
            'name' => $studly,
            'alias' => $kebab,
            'description' => '',
            'version' => '0.1.0',
            'providers' => [],

            // Map<string, DependencySpec> → must be an object
            'dependencies' => new stdClass(),

            // Array<HostConfig>
            'hostConfig' => [],

            // { items: UiItem[] }
            'uiConfig' => [
                'items' => [],
            ],

            // { dir: string; glob?: string }
            'routes' => [
                'dir' => 'routes',
                'glob' => '**/*.routes.json',
            ],

            // Record<Slug, ExportDefinition> → must be an object
            'exports' => new stdClass(),
        ];
    }

    /**
     * Tiny wrapper to safely decode Guzzle responses.
     * @throws JsonException
     */
    protected function getJson($response): array
    {
        $code = $response->getStatusCode();
        $body = (string)$response->getBody();
        if ($code < 200 || $code >= 300) {
            $this->error("Host API error ($code): " . ($body ?: ''));
            return ['ok' => false];
        }
        return json_decode($body, true, 512, JSON_THROW_ON_ERROR) ?? [];
    }

    protected function scaffoldViewAssets(string $pluginPath): void
    {
        // Inertia entry + sample page
        $this->files->ensureDirectoryExists("$pluginPath/resources/inertia/ts/Pages");
        $this->files->put(
            "$pluginPath/resources/inertia/ts/app.tsx",
            <<<TS
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

        // Embed sample component
        $this->files->ensureDirectoryExists("$pluginPath/resources/embed/ts/pages");
        $this->files->ensureDirectoryExists("$pluginPath/resources/embed/ts/addons");
        $this->files->put(
            "$pluginPath/resources/embed/ts/Hello.tsx",
            "export default () => <div className='p-2'>Embedded Hello!</div>;"
        );

        // vite input map
        $this->files->put(
            "$pluginPath/resources/embed/vite.input.js",
            $this->renderStub("viteInputGen")
        );

        // vite.config.js
        $this->files->put(
            "$pluginPath/vite.config.js",
            $this->renderStub("viteConfig")
        );

        // tsconfig.json
        $this->files->put(
            "$pluginPath/tsconfig.json",
            $this->renderStub("tsconfig")
        );

        // package.json (bare)
        $this->files->put(
            "$pluginPath/package.json",
            <<<JSON
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
        $cmd = [
            'npm', 'install', '-D',
            'vite', 'typescript', '@vitejs/plugin-react',
            '@types/react', '@types/react-dom',
            'tailwindcss'
        ];
        (new Process($cmd, $cwd))->setTimeout(600)->run(fn($t, $b) => $this->output->write($b));
    }

    protected function runTailwindInit(string $cwd): void
    {
        $this->line('Initializing Tailwind config…');
        (new Process(['npx', 'tailwindcss', 'init', '-p'], $cwd))->run();
    }
}