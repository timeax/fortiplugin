<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Contracts;

/**
 * Emits audit records for permission decisions (and optionally ingestion events).
 * Implementations may redact sensitive fields based on assignment or host policy.
 */
interface AuditEmitterInterface
{
    /**
     * Record a decision.
     *
     * @param string $phase   'check' | 'ingest'
     * @param string $type    db|file|notification|module|network|codec|route
     * @param int    $pluginId
     * @param array  $request   Original request payload (sanitized if needed).
     * @param array  $decision  Result array (allowed/reason/matched/context).
     * @param array  $options   Optional: ['redact_fields'=>string[], 'tags'=>string[]]
     * @return void
     */
    public function record(string $phase, string $type, int $pluginId, array $request, array $decision, array $options = []): void;
}