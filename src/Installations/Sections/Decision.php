<?php

namespace Timeax\FortiPlugin\Installations\Sections;


use Timeax\FortiPlugin\Installations\Enums\Install;

trait Decision
{

    private function persistDecision(Install $decision, string $reason, ?array $tokenSummary = null): void
    {
        $this->log->writeSection('decisions', array_filter([
            'status' => $decision->value,
            'reason' => $reason,
            'token' => $tokenSummary,
        ]));
    }
}