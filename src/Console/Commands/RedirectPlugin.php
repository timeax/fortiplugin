<?php

namespace Timeax\FortiPlugin\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use JsonException;
use Throwable;
use Timeax\FortiPlugin\Traits\PluginHandshake;

class RedirectPlugin extends Command
{
    use PluginHandshake;

    protected $signature = 'fp:redirect
        {name : Plugin directory name, e.g., OrdersPlugin}
        {--force : Overwrite if necessary}';

    protected $description = 'Redirect an existing plugin to a new host, creating a new placeholder on the new host.';

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

        $name = $this->argument('name');
        $base = config('fortiplugin.dev_directory', 'Plugins');
        $path = $base . DIRECTORY_SEPARATOR . $name;

        if (!$this->files->exists($path)) {
            $this->error("Plugin '$name' does not exist locally.");
            return self::FAILURE;
        }

        // 2) Read existing configuration to get slug and name
        $fortiConfigPath = "$path/fortiplugin.json";
        if (!$this->files->exists($fortiConfigPath)) {
            $this->error("fortiplugin.json not found in '$name'.");
            return self::FAILURE;
        }

        try {
            $fortiConfig = json_decode($this->files->get($fortiConfigPath), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $this->error("Invalid fortiplugin.json: " . $e->getMessage());
            return self::FAILURE;
        }

        $studly = $fortiConfig['name'] ?? $name;
        $kebab = $fortiConfig['alias'] ?? Str::kebab($studly);

        // 3) Perform Handshake and Init Placeholder
        $handshakeResult = $this->performHandshakeAndInit($this, $this->files, $path, $studly, $kebab, $session);
        if (!$handshakeResult) {
            return self::FAILURE;
        }

        $pluginKey = $handshakeResult['pluginKey'];
        $author = $handshakeResult['author'];

        // 4) Update publish.json
        $publishPath = $path . "/publish.json";
        
        // We overwrite publish.json because we are redirecting to a new host
        $this->files->put($publishPath, json_encode([
            'host' => $session['host'],
            'plugin_slug' => $kebab,
            'plugin_key' => $pluginKey,
            'author' => $author,
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->info("Plugin '$studly' redirected to host '{$session['host']}'.");
        return self::SUCCESS;
    }
}
