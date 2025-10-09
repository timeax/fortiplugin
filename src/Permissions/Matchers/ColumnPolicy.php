<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Matchers;

final class ColumnPolicy
{
    /**
     * @param string        $action   "select"|"insert"|"update"|...
     * @param string[]|null $requestedColumns  columns the plugin wants to touch (null => treat as empty requested set)
     * @param array{all?:array,writable?:array}|null $policy host policy for the model (any missing/null key => unknown/unconstrained)
     * @return array{ok:bool,reason?:string,diff?:array{not_allowed?:string[],not_writable?:string[]}}
     */
    public function check(string $action, ?array $requestedColumns, ?array $policy): array
    {
        $requested = $this->normalizeList($requestedColumns);
        // No columns requested often means "any" (e.g., delete/truncate/transaction) → nothing to check.
        if ($requested === [] && !in_array($action, ['select','insert','update'], true)) {
            return ['ok' => true, 'reason' => null];
        }

        $all      = $this->normalizeList($policy['all']      ?? null);
        $writable = $this->normalizeList($policy['writable'] ?? null);

        // SELECT must be ⊆ all (when known)
        if ($action === 'select') {
            if ($all !== null) {
                $notAllowed = array_values(array_diff($requested, $all));
                if ($notAllowed !== []) {
                    return ['ok' => false, 'reason' => 'columns_not_in_all_policy', 'diff' => ['not_allowed' => $notAllowed]];
                }
            }
            return ['ok' => true, 'reason' => null];
        }

        // INSERT/UPDATE must be ⊆ writable (when known) AND (if 'all' known) also ⊆ all
        if ($action === 'insert' || $action === 'update') {
            if ($writable !== null) {
                $notWritable = array_values(array_diff($requested, $writable));
                if ($notWritable !== []) {
                    return ['ok' => false, 'reason' => 'columns_not_writable', 'diff' => ['not_writable' => $notWritable]];
                }
            }
            if ($all !== null) {
                $notAllowed = array_values(array_diff($requested, $all));
                if ($notAllowed !== []) {
                    return ['ok' => false, 'reason' => 'columns_not_in_all_policy', 'diff' => ['not_allowed' => $notAllowed]];
                }
            }
            return ['ok' => true, 'reason' => null];
        }

        // Other actions (delete/truncate/transaction/grouped_queries...) don't use column lists.
        return ['ok' => true, 'reason' => null];
    }

    /**
     * @return string[]|null  (null means "unknown/unconstrained")
     */
    private function normalizeList(?array $list): ?array
    {
        if ($list === null) return null;
        $out = [];
        foreach ($list as $c) {
            if (!is_string($c) || $c === '') continue;
            $out[] = $c;
        }
        return array_values(array_unique($out));
    }
}