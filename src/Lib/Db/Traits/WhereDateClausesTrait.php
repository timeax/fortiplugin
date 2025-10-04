<?php

namespace Timeax\FortiPlugin\Lib\Db\Traits;


use Timeax\FortiPlugin\Lib\Db\PluginQueryBuilder;

/**
 * Trait WhereDateClausesTrait
 * Adds Eloquent's date/time-based where clause helpers to your query builder.
 */
trait WhereDateClausesTrait
{
    /**
     * Add a "where past" clause to the query.
     * Selects rows where the date/time column is in the past.
     * @param string $column
     * @return WhereDateClausesTrait|PluginQueryBuilder
     */
    public function wherePast(string $column): self
    {
        return $this->addStep('wherePast', [$column]);
    }

    /**
     * Add a "where future" clause to the query.
     * Selects rows where the date/time column is in the future.
     * @param string $column
     * @return WhereDateClausesTrait|PluginQueryBuilder
     */
    public function whereFuture(string $column): self
    {
        return $this->addStep('whereFuture', [$column]);
    }

    /**
     * Add a "where now or past" clause to the query.
     * Selects rows where the date/time is now or earlier.
     * @param string $column
     * @return WhereDateClausesTrait|PluginQueryBuilder
     */
    public function whereNowOrPast(string $column): self
    {
        return $this->addStep('whereNowOrPast', [$column]);
    }

    /**
     * Add a "where now or future" clause to the query.
     * Selects rows where the date/time is now or later.
     * @param string $column
     * @return WhereDateClausesTrait|PluginQueryBuilder
     */
    public function whereNowOrFuture(string $column): self
    {
        return $this->addStep('whereNowOrFuture', [$column]);
    }

    /**
     * Add a "where today" clause to the query.
     * Selects rows where the date column is today.
     * @param string $column
     * @return WhereDateClausesTrait|PluginQueryBuilder
     */
    public function whereToday(string $column): self
    {
        return $this->addStep('whereToday', [$column]);
    }

    /**
     * Add a "where before today" clause to the query.
     * Selects rows where the date is before today.
     * @param string $column
     * @return WhereDateClausesTrait|PluginQueryBuilder
     */
    public function whereBeforeToday(string $column): self
    {
        return $this->addStep('whereBeforeToday', [$column]);
    }

    /**
     * Add a "where after today" clause to the query.
     * Selects rows where the date is after today.
     * @param string $column
     * @return WhereDateClausesTrait|PluginQueryBuilder
     */
    public function whereAfterToday(string $column): self
    {
        return $this->addStep('whereAfterToday', [$column]);
    }

    /**
     * Add a "where today or before" clause to the query.
     * Selects rows where the date is today or before.
     * @param string $column
     * @return WhereDateClausesTrait|PluginQueryBuilder
     */
    public function whereTodayOrBefore(string $column): self
    {
        return $this->addStep('whereTodayOrBefore', [$column]);
    }

    /**
     * Add a "where today or after" clause to the query.
     * Selects rows where the date is today or after.
     * @param string $column
     * @return WhereDateClausesTrait|PluginQueryBuilder
     */
    public function whereTodayOrAfter(string $column): self
    {
        return $this->addStep('whereTodayOrAfter', [$column]);
    }

    // OR variants

    public function orWherePast(string $column): self
    {
        return $this->addStep('orWherePast', [$column]);
    }

    public function orWhereFuture(string $column): self
    {
        return $this->addStep('orWhereFuture', [$column]);
    }

    public function orWhereNowOrPast(string $column): self
    {
        return $this->addStep('orWhereNowOrPast', [$column]);
    }

    public function orWhereNowOrFuture(string $column): self
    {
        return $this->addStep('orWhereNowOrFuture', [$column]);
    }

    public function orWhereToday(string $column): self
    {
        return $this->addStep('orWhereToday', [$column]);
    }

    public function orWhereBeforeToday(string $column): self
    {
        return $this->addStep('orWhereBeforeToday', [$column]);
    }

    public function orWhereAfterToday(string $column): self
    {
        return $this->addStep('orWhereAfterToday', [$column]);
    }

    public function orWhereTodayOrBefore(string $column): self
    {
        return $this->addStep('orWhereTodayOrBefore', [$column]);
    }

    public function orWhereTodayOrAfter(string $column): self
    {
        return $this->addStep('orWhereTodayOrAfter', [$column]);
    }
}