<?php

namespace Timeax\FortiPlugin\Traits;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use JsonException;
use Throwable;

trait PluginHandshake
{
    use AuthenticateSession, Stubber;

    /**
     * Perform the handshake with the host and initialize the plugin placeholder.
     *
     * @param Command $command The command instance calling this method.
     * @param Filesystem $files The filesystem instance.
     * @param string $path The local path to the plugin.
     * @param string $studly The studly case name of the plugin.
     * @param string $kebab The kebab case alias of the plugin.
     * @param array $session The active session data.
     * @return array|null Returns an array with ['pluginKey' => ..., 'author' => ...] on success, or null on failure.
     * @throws JsonException
     * @throws Throwable
     */
    protected function performHandshakeAndInit(
        Command $command,
        Filesystem $files,
        string $path,
        string $studly,
        string $kebab,
        array $session
    ): ?array {
        $client = $this->getHttp();
        if (!$client) {
            $command->error('Could not create API client from your session.');
            return null;
        }

        // 1) Contact host: handshake (policy + verify + signature block)
        $handshake = $this->getJson($client->get('/forti/handshake'), $command);
        if (!($handshake['ok'] ?? false)) {
            $command->error('Handshake failed.');
            return null;
        }

        $signatureBlock = $handshake['signature_block'] ?? null;
        if (!$signatureBlock) {
            $command->warn('Host did not return a signature_block. Continuing without it.');
        }

        // 2) Ask/derive author info (prefer from session)
        $author = [
            'name' => $session['name'] ?? $command->ask('Author name'),
            'email' => $session['email'] ?? $command->ask('Author email'),
        ];

        // 3) Init placeholder (+ placeholder token)
        $init = $this->getJson($client->post('/forti/handshake/init', [
            'slug' => $kebab,
            'name' => $studly,
        ]), $command);

        if (!($init['ok'] ?? false)) {
            $command->error('Failed to create placeholder on host.');
            return null;
        }

        $placeholder = $init['placeholder'] ?? [];
        $placeholderId = $placeholder['id'] ?? null;
        $pluginKey = $placeholder['key'] ?? null;  // unique_key from server
        $phToken = $init['token'] ?? null;

        if (!$pluginKey) {
            $command->error('Host did not return a plugin key for the placeholder.');
            return null;
        }

        // 4) Write/Update .internal files
        $internalDir = "$path/.internal";
        if (!$files->exists($internalDir)) {
            $files->makeDirectory($internalDir, 0755, true);
        }

        // 4a) Host-provided signature block into Config.php (via stub)
        $psr4Root = $init['psr4_root'] ?? 'Plugins'; // e.g. "Plugins"
        $files->put(
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

        // 4b) Store placeholder token (single-use raw)
        if ($phToken) {
            $files->put(
                "$internalDir/placeholder.token.json",
                json_encode(['token' => $phToken, 'placeholder_id' => $placeholderId, 'slug' => $kebab], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT)
            );
        }

        return [
            'pluginKey' => $pluginKey,
            'author' => $author,
            'psr4Root' => $psr4Root,
        ];
    }

    /**
     * Tiny wrapper to safely decode Guzzle responses.
     * @throws JsonException
     */
    protected function getJson($response, Command $command): array
    {
        $code = $response->getStatusCode();
        $body = (string)$response->getBody();
        if ($code < 200 || $code >= 300) {
            $command->error("Host API error ($code): " . ($body ?: ''));
            return ['ok' => false];
        }
        return json_decode($body, true, 512, JSON_THROW_ON_ERROR) ?? [];
    }
}
