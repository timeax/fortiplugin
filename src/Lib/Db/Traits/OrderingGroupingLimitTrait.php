<?php

namespace Timeax\FortiPlugin\Lib\Db\Traits;


use Timeax\FortiPlugin\Lib\Db\PluginQueryBuilder;

/**
 * Trait OrderingGroupingLimitTrait
 * Implements ordering, grouping, limit, and offset query builder methods from Laravel 12.x.
 */
trait OrderingGroupingLimitTrait
{
    // ---- ORDERING ----

    /**
     * Add an "order by" clause to the query.
     *
     * @param string $column
     * @param string $direction
     * @return OrderingGroupingLimitTrait|PluginQueryBuilder
     */
    public function orderBy(string $column, string $direction = 'asc'): self
    {
        return $this->addStep('orderBy', [$column, $direction]);
    }

    /**
     * Add an "order by desc" clause to the query.
     *
     * @param string $column
     * @return OrderingGroupingLimitTrait|PluginQueryBuilder
     */
    public function orderByDesc(string $column): self
    {
        return $this->addStep('orderByDesc', [$column]);
    }

    /**
     * Add an "or order by" clause to the query.
     *
     * @param string $column
     * @param string $direction
     * @return OrderingGroupingLimitTrait|PluginQueryBuilder
     */
    public function orOrderBy(string $column, string $direction = 'asc'): self
    {
        return $this->addStep('orOrderBy', [$column, $direction]);
    }

    /**
     * Order the results by the newest records first.
     *
     * @param string|null $column
     * @return OrderingGroupingLimitTrait|PluginQueryBuilder
     */
    public function latest(string $column = null): self
    {
        return $this->addStep('latest', [$column]);
    }

    /**
     * Order the results by the oldest records first.
     *
     * @param string|null $column
     * @return OrderingGroupingLimitTrait|PluginQueryBuilder
     */
    public function oldest(string $column = null): self
    {
        return $this->addStep('oldest', [$column]);
    }

    /**
     * Order the results randomly.
     *
     * @return OrderingGroupingLimitTrait|PluginQueryBuilder
     */
    public function inRandomOrder(): self
    {
        return $this->addStep('inRandomOrder', []);
    }

    /**
     * Remove all orderings and apply the given ordering(s).
     *
     * @param mixed ...$columns
     * @return OrderingGroupingLimitTrait|PluginQueryBuilder
     */
    public function reorder(...$columns): self
    {
        return $this->addStep('reorder', $columns);
    }

    /**
     * Reverse the current orderings of the query.
     *
     * @return OrderingGroupingLimitTrait|PluginQueryBuilder
     */
    public function reverse(): self
    {
        return $this->addStep('reverse', []);
    }

    // ---- GROUPING ----

    /**
     * Add a "group by" clause to the query.
     *
     * @param mixed ...$groups
     * @return OrderingGroupingLimitTrait|PluginQueryBuilder
     */
    public function groupBy(...$groups): self
    {
        return $this->addStep('groupBy', $groups);
    }

    /**
     * Add a "having" clause to the query.
     *
     * @param string $column
     * @param string|null $operator
     * @param mixed|null $value
     * @return OrderingGroupingLimitTrait|PluginQueryBuilder
     */
    public function having(string $column, ?string $operator = null, mixed $value = null): self
    {
        return $this->addStep('having', func_get_args());
    }

    /**
     * Add an "or having" clause to the query.
     *
     * @param string $column
     * @param string|null $operator
     * @param mixed|null $value
     * @return OrderingGroupingLimitTrait|PluginQueryBuilder
     */
    public function orHaving(string $column, ?string $operator = null, mixed $value = null): self
    {
        return $this->addStep('orHaving', func_get_args());
    }

    /**
     * Add a "having between" clause to the query.
     *
     * @param string $column
     * @param array $values
     * @return OrderingGroupingLimitTrait|PluginQueryBuilder
     */
    public function havingBetween(string $column, array $values): self
    {
        return $this->addStep('havingBetween', [$column, $values]);
    }

    /**
     * Add a "having null" clause to the query.
     *
     * @param string $column
     * @return OrderingGroupingLimitTrait|PluginQueryBuilder
     */
    public function havingNull(string $column): self
    {
        return $this->addStep('havingNull', [$column]);
    }

    /**
     * Add a "having not null" clause to the query.
     *
     * @param string $column
     * @return OrderingGroupingLimitTrait|PluginQueryBuilder
     */
    public function havingNotNull(string $column): self
    {
        return $this->addStep('havingNotNull', [$column]);
    }

    /**
     * Add a "having in" clause to the query.
     *
     * @param string $column
     * @param array $values
     * @return OrderingGroupingLimitTrait|PluginQueryBuilder
     */
    public function havingIn(string $column, array $values): self
    {
        return $this->addStep('havingIn', [$column, $values]);
    }

    /**
     * Add a "having not in" clause to the query.
     *
     * @param string $column
     * @param array $values
     * @return OrderingGroupingLimitTrait|PluginQueryBuilder
     */
    public function havingNotIn(string $column, array $values): self
    {
        return $this->addStep('havingNotIn', [$column, $values]);
    }

    /**
     * Add a raw having clause to the query.
     *
     * @param string $sql
     * @param array $bindings
     * @return OrderingGroupingLimitTrait|PluginQueryBuilder
     */
    public function havingRaw(string $sql, array $bindings = []): self
    {
        return $this->addStep('havingRaw', [$sql, $bindings]);
    }

    // ---- LIMIT & OFFSET ----

    /**
     * Limit the number of records returned.
     *
     * @param int $value
     * @return OrderingGroupingLimitTrait|PluginQueryBuilder
     */
    public function limit(int $value): self
    {
        return $this->addStep('limit', [$value]);
    }

    /**
     * Offset the results by a given number.
     *
     * @param int $value
     * @return OrderingGroupingLimitTrait|PluginQueryBuilder
     */
    public function offset(int $value): self
    {
        return $this->addStep('offset', [$value]);
    }

    /**
     * Alias for limit().
     *
     * @param int $value
     * @return OrderingGroupingLimitTrait|PluginQueryBuilder
     */
    public function take(int $value): self
    {
        return $this->addStep('take', [$value]);
    }

    /**
     * Alias for offset().
     *
     * @param int $value
     * @return OrderingGroupingLimitTrait|PluginQueryBuilder
     */
    public function skip(int $value): self
    {
        return $this->addStep('skip', [$value]);
    }

    /**
     * Shortcut for pagination using offset and limit.
     *
     * @param int $page
     * @param int $perPage
     * @return OrderingGroupingLimitTrait|PluginQueryBuilder
     */
    public function forPage(int $page, int $perPage = 15): self
    {
        return $this->addStep('forPage', [$page, $perPage]);
    }
}