<?php

namespace Timeax\FortiPlugin\Console\Commands;

use Illuminate\Console\Command;
use JsonException;
use SodiumException;
use Timeax\FortiPlugin\Support\CliSessionManager;

class ChangeHostCommand extends Command
{
    protected $signature = 'fp:change {aliasOrHost?}';
    protected $description = 'Switch to a different saved host session (by alias or domain)';

    /**
     * @throws JsonException
     * @throws SodiumException
     */
    public function handle(): int
    {
        $sessions = CliSessionManager::listHosts();
        if (empty($sessions)) {
            $this->error('No saved hosts/sessions found. Please login to at least one host first.');
            return 1;
        }

        $aliasOrHost = $this->argument('aliasOrHost');

        // If no argument, or not found, show choices
        if (!$aliasOrHost || !self::resolveAlias($aliasOrHost, $sessions)) {
            $options = [];
            foreach ($sessions as $alias => $info) {
                $options[] = "$alias ({$info['host']})";
            }
            $picked = $this->choice('Select a session alias or host to activate', $options);
            // Parse alias back from "alias (host)"
            $aliasOrHost = explode(' ', $picked)[0];
        }

        $realAlias = self::resolveAlias($aliasOrHost, $sessions);

        if ($realAlias && CliSessionManager::setCurrent($realAlias)) {
            $session = $sessions[$realAlias];
            $this->info("[✓] Switched to alias: $realAlias ({$session['host']})");
            return 0;
        }

        $this->error("No session found for alias or host: $aliasOrHost");
        return 1;
    }

    /**
     * Given an alias or a host, find the corresponding alias in $sessions
     */
    protected static function resolveAlias($aliasOrHost, $sessions): int|string|null
    {
        foreach ($sessions as $alias => $info) {
            if ($alias === $aliasOrHost || $info['host'] === $aliasOrHost) {
                return $alias;
            }
        }
        return null;
    }
}
