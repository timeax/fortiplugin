<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Ingestion\Traits;

/**
 * Provides a common way to build the assignment-level metadata for ingestors.
 *
 * Usage pattern inside an ingestor's ingest():
 *  - $this->setMetaRule($rule);
 *  - $meta = $this->getMeta(); // or $this->getMeta($overrides)
 */
trait HasIngestionMeta
{
    /** @var array<string,mixed> */
    protected array $metaRule = [];

    /**
     * Set the current normalized rule used to derive the meta payload.
     * @param array<string,mixed> $rule
     */
    protected function setMetaRule(array $rule): void
    {
        $this->metaRule = $rule;
    }

    /**
     * Build the standard meta array from the current rule and merge optional overrides.
     * @param array<string,mixed>|null $more Optional overrides to merge (overrides win).
     * @return array<string,mixed>
     */
    protected function getMeta(?array $more = null): array
    {
        $rule = $this->metaRule ?? [];

        $meta = [
            'actions'       => (array)($rule['actions'] ?? []),
            'audit'         => $rule['audit'] ?? null,
            'conditions'    => $rule['conditions'] ?? null,
            'constraints'   => $rule['constraints'] ?? null,
            'justification' => $rule['justification'] ?? null,
        ];

        if ($more !== null) {
            // Merge overrides deeply so nested arrays (e.g., constraints) can be extended/overridden
            $meta = array_replace_recursive($meta, $more);
        }

        return $meta;
    }
}
