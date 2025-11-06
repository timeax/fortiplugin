<?php

namespace Timeax\FortiPlugin\Installations\Sections;

use Timeax\FortiPlugin\Installations\Enums\Install;

trait Decision
{
    /**
     * @throws JsonException
     */
    private function persistDecision(string $pluginDir, Install $decision, string $reason, ?array $tokenSummary = null): void
    {
        $path = $this->installationLogPath($pluginDir);
        $this->afs->ensureParentDirectory($path);

        $doc = $this->afs->fs()->exists($path) ? $this->afs->fs()->readJson($path) : [];
        $doc['decision'] = array_filter([
            'status' => $decision->value,
            'reason' => $reason,
            'token'  => $tokenSummary,
        ]);
        $this->afs->writeJsonAtomic($path, $doc, true);
    }
}