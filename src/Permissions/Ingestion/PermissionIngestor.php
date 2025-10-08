<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Ingestion;

use Throwable;
use Timeax\FortiPlugin\Permissions\Contracts\PermissionIngestorInterface;
use Timeax\FortiPlugin\Permissions\Contracts\PermissionRepositoryInterface;
use Timeax\FortiPlugin\Permissions\Ingestion\Dto\IngestSummary;
use Timeax\FortiPlugin\Permissions\Ingestion\Dto\RuleIngestResult;
use Timeax\FortiPlugin\Permissions\Registry\PermissionRegistry;

/**
 * Orchestrates manifest ingestion by dispatching to per-type ingestors via the registry.
 * Expects manifest is already validated & normalized.
 */
final readonly class PermissionIngestor
{
    public function __construct(
        private PermissionRepositoryInterface $repo,
        private PermissionRegistry            $registry
    ) {}

    /**
     * Ingest all rules for a plugin (idempotent).
     *
     * @param int $pluginId
     * @param array{
     *   required_permissions?: array<int, array<string,mixed>>,
     *   optional_permissions?: array<int, array<string,mixed>>
     * } $manifest
     * @return IngestSummary
     */
    public function ingest(int $pluginId, array $manifest): IngestSummary
    {
        $created  = 0;
        $linked   = 0;
        /** @var RuleIngestResult[] $items */
        $items    = [];
        $warnings = [];

        foreach (['required_permissions', 'optional_permissions'] as $bucket) {
            $rules = $manifest[$bucket] ?? [];
            if (!is_array($rules) || $rules === []) {
                continue;
            }

            foreach (array_values($rules) as $i => $rule) {
                $path = '$.' . $bucket . '[' . $i . ']';
                try {
                    $type = (string)($rule['type'] ?? '');
                    $ingestor = $this->registry->ingestorFor($type);

                    if (!$ingestor instanceof PermissionIngestorInterface) {
                        $warnings[] = "$path: no ingestor registered for type '$type'";
                        continue;
                    }

                    $dto = $ingestor->ingest($pluginId, $rule, $this->repo);
                    $items[] = $dto;

                    if ($dto->created) { $created++; }
                    if ($dto->assigned) { $linked++; }

                    if ($dto->warning !== null && $dto->warning !== '') {
                        $warnings[] = "$path: {$dto->warning}";
                    }
                } catch (Throwable $e) {
                    $warnings[] = "$path: " . $e->getMessage();
                }
            }
        }

        return new IngestSummary($created, $linked, $items, $warnings);
    }
}