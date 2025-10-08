<?php

namespace Timeax\FortiPlugin\Console\Commands;

use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Console\Command;
use JsonException;
use SodiumException;
use Timeax\FortiPlugin\Support\CliSessionManager;
use GuzzleHttp\Client;

class LogoutCommand extends Command
{
    protected $signature = 'secure-plugin logout {--host=}';
    protected $description = 'Clear saved CLI session and logout.';

    /**
     * @throws SodiumException
     * @throws JsonException
     * @throws GuzzleException
     */
    public function handle(): int
    {
        $selection = $this->option('host') ?: $this->choice('Select host to logout from', $this->getSavedHosts());

        // Resolve selection (could be alias or host) to a session
        $session = $selection ? CliSessionManager::getSession($selection) : null;
        if (!$session) {
            $this->error('Failed to find a saved session for the provided host/alias.');
            return 1;
        }

        $host = $session['host'] ?? null;
        $token = $session['token'] ?? null;
        if (!$host || !$token) {
            $this->error('Saved session is missing host or token.');
            return 1;
        }

        $apiBase = 'https://' . $host . '/api/';
        $client = new Client(['base_uri' => $apiBase, 'http_errors' => false]);
        $response = $client->post('forti/logout', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
            ],
        ]);

        // Regardless of API response, clear the local session for the selected alias/host
        CliSessionManager::removeHost($selection);

        $status = $response->getStatusCode();
        if ($status >= 200 && $status < 300) {
            $this->info("[✓] Logged out via API and local session for $host cleared.");
            return 0;
        }

        $this->warn("API logout responded with status $status. Local session was cleared.");
        return 0;
    }

    // Helper to get all saved hosts (we list aliases for clarity)

    /**
     * @throws SodiumException
     * @throws JsonException
     */
    protected function getSavedHosts(): array
    {
        $sessions = CliSessionManager::loadSessions();
        return array_keys($sessions['hosts']);
    }
}
