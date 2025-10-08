<?php

namespace Timeax\FortiPlugin\Console\Commands;

use Illuminate\Console\Command;
use JsonException;
use SodiumException;
use Timeax\FortiPlugin\Support\CliSessionManager;

class ListHostsCommand extends Command
{
    protected $signature = 'secure-plugin hosts';
    protected $description = 'List all saved hosts and current session';

    /**
     * @throws SodiumException
     * @throws JsonException
     */
    public function handle(): void
    {
        $sessions = CliSessionManager::loadSessions();
        $current = $sessions['current'] ?? null;
        $this->info('Saved Hosts:');
        foreach ($sessions['hosts'] as $name => $details) {
            $this->line(($name === $current ? '[*]' : '[-]') . " $name: " . $details['host']);
        }
    }
}
W