<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Audit;

use Illuminate\Support\Arr;
use Psr\Log\LoggerInterface;
use Throwable;
use Timeax\FortiPlugin\Models\PluginAuditLog;
use Timeax\FortiPlugin\Permissions\Contracts\AuditEmitterInterface;

/**
 * Default AuditEmitter:
 *  - Redacts request/decision using Redactor + optional explicit fields.
 *  - Persists to plugin_audit_logs (actor fields left null unless extended).
 *  - Optionally mirrors to a PSR logger (structured).
 */
final readonly class AuditEmitter implements AuditEmitterInterface
{
    public function __construct(
        private Redactor         $redactor,
        private ?LoggerInterface $logger = null
    )
    {
    }

    /**
     * @inheritDoc
     */
    public function record(string $phase, string $type, int $pluginId, array $request, array $decision, array $options = []): void
    {
        // Build a short resource string (for quick scanning in tables)
        $resource = $this->resourceSummary($type, $request);

        // Redaction
        $explicit = array_values(array_filter((array)Arr::get($options, 'redact_fields', [])));
        $reqRed = $this->redactor->redact($request, $explicit);
        $decRed = $this->redactor->redact($decision, $explicit);

        $action = $phase; // 'check' or 'ingest'
        if ($phase === 'check') {
            $action = ($decision['allowed'] ?? false) ? 'allow' : 'deny';
        }

        // Assemble context blob (kept small; detailed payloads inside)
        $context = [
            'phase' => $phase,
            'request' => $reqRed,
            'decision' => $decRed,
            'tags' => Arr::get($options, 'tags'),
        ];

        // Persist to DB (non-fatal)
        try {
            PluginAuditLog::query()->create([
                'plugin_id' => $pluginId,
                'type' => $type,      // domain: db|file|...
                'action' => $action,    // allow|deny|ingest
                'resource' => $resource,  // terse summary
                'context' => $context,
                // actor/actor_author_id left null; can be filled by a decorator/extended emitter
            ]);
        } catch (Throwable $e) {
            // Swallow DB errors; still attempt to log
            $this->logSafe('warning', 'fortiplugin.audit.db_error', [
                'plugin' => $pluginId,
                'type' => $type,
                'phase' => $phase,
                'error' => $e->getMessage(),
            ]);
        }

        // Mirror to logs (non-fatal)
        $this->logSafe('info', 'fortiplugin.audit', [
            'plugin' => $pluginId,
            'type' => $type,
            'phase' => $phase,
            'action' => $action,
            'resource' => $resource,
            'context' => $context,
        ]);
    }

    private function resourceSummary(string $type, array $request): string
    {
        // The request may be either a DTO->toArray() or a raw associative array.
        $a = $request;

        return match ($type) {
            'db' => sprintf(
                'db:%s %s%s',
                $a['action'] ?? '',
                $a['model'] ?? $a['table'] ?? '',
                isset($a['columns']) && is_array($a['columns']) && $a['columns'] !== []
                    ? ' [' . implode(',', array_slice(array_map('strval', $a['columns']), 0, 5)) . (count($a['columns']) > 5 ? '…' : '') . ']'
                    : ''
            ),
            'file' => sprintf(
                'file:%s %s',
                $a['action'] ?? '',
                $a['path'] ?? ''
            ),
            'network' => (static function () use ($a) {
                $m = strtoupper((string)($a['method'] ?? 'GET'));
                $u = (string)($a['url'] ?? '');
                $host = parse_url($u, PHP_URL_HOST) ?: '';
                $path = parse_url($u, PHP_URL_PATH) ?: '/';
                return "net:$m $host$path";
            })(),
            'notification' => sprintf(
                'notify:%s %s',
                $a['action'] ?? '',
                $a['channel'] ?? ''
            ),
            'module' => sprintf(
                'module: %s::%s',
                $a['module'] ?? '',
                $a['api'] ?? ''
            ),
            'codec' => sprintf('codec:%s', $a['method'] ?? ''),
            'route' => sprintf('route:%s', $a['routeId'] ?? ''),
            default => $type
        };
    }

    private function logSafe(string $level, string $message, array $context = []): void
    {
        if (!$this->logger) return;
        try {
            $this->logger->log($level, $message, $context);
        } catch (Throwable) {
            // ignore
        }
    }
}