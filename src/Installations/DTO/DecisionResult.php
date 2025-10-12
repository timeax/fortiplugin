<?php

namespace Timeax\FortiPlugin\Installations\DTO;

class DecisionResult
{
    public function __construct(
        public string $status, // 'installed' | 'ask' | 'break'
        public mixed $summary = null,
        public ?string $tokenEncrypted = null,
        public ?string $expiresAt = null,
    ) {}
}
