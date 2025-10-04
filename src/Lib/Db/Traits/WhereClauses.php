<?php

namespace Timeax\FortiPlugin\Lib\Db\Traits;

use Closure;
use InvalidArgumentException;
use Timeax\FortiPlugin\Lib\Db\PluginQueryBuilder;

/**
 * @method addStep(string $type, array $args)
 * @method wrapCallbackWithDbMethods($alias, Closure $pluginCallback)
 * @property $modelAlias
 */
trait WhereClauses
{
    use WhereDateClausesTrait;

    /**
     * Add a where clause to the query.
     * @param array|string|Closure $field
     * @param mixed|null $operatorOrValue
     * @param mixed|null $value
     * @return WhereClauses|PluginQueryBuilder
     */
    public function where(array|string|closure $field, mixed $operatorOrValue = null, mixed $value = null): self
    {
        if ($field instanceof Closure) {
            $this->addStep("where", [$this->wrapCallbackWithDbMethods($this->modelAlias, $field)]);
            return $this;
        }
        // Support array of conditions (['field' => value, ...])
        if (is_array($field)) {
            foreach ($field as $col => $val) {
                $this->addStep('where', [$col, '=', $val]);
            }
            return $this;
        }

        // Support where('field', 'value') and where('field', 'op', 'value')
        $args = func_get_args();
        if (count($args) === 2) {
            $this->addStep('where', [$field, '=', $operatorOrValue]);
        } elseif (count($args) === 3) {
            $this->addStep('where', [$field, $operatorOrValue, $value]);
        } else {
            throw new InvalidArgumentException("Invalid where() arguments.");
        }
        return $this;
    }

    /**
     * Add an OR WHERE clause to the query.
     * Supports orWhere('field', 'value'), orWhere('field', 'op', 'value').
     *
     * @param mixed $field
     * @param mixed|null $operatorOrValue
     * @param mixed|null $value
     * @return WhereClauses|PluginQueryBuilder
     */
    public function orWhere(mixed $field, mixed $operatorOrValue = null, mixed $value = null): self
    {
        if ($field instanceof Closure) {
            $this->addStep("orWhere", [$this->wrapCallbackWithDbMethods($this->modelAlias, $field)]);
            return $this;
        }

        if (is_array($field)) {
            foreach ($field as $col => $val) {
                $this->addStep('orWhere', [$col, '=', $val]);
            }
            return $this;
        }

        $args = func_get_args();
        if (count($args) === 2) {
            $this->addStep('orWhere', [$field, '=', $operatorOrValue]);
        } elseif (count($args) === 3) {
            $this->addStep('orWhere', [$field, $operatorOrValue, $value]);
        } else {
            throw new InvalidArgumentException("Invalid orWhere() arguments.");
        }
        return $this;
    }

    public function whereIn($column, $values): self
    {
        return $this->addStep('whereIn', [$column, $values]);
    }

    public function whereNotIn($column, $values): self
    {
        return $this->addStep('whereNotIn', [$column, $values]);
    }

    public function whereNull($column): self
    {
        return $this->addStep('whereNull', [$column]);
    }

    public function whereNotNull($column): self
    {
        return $this->addStep('whereNotNull', [$column]);
    }

    public function whereBetween($column, array $values): self
    {
        return $this->addStep('whereBetween', [$column, $values]);
    }

    public function whereNotBetween($column, array $values): self
    {
        return $this->addStep('whereNotBetween', [$column, $values]);
    }

    public function whereDate($column, $operatorOrValue = null, $value = null): self
    {
        return $this->addStep('whereDate', func_get_args());
    }

    public function whereMonth($column, $value): self
    {
        return $this->addStep('whereMonth', [$column, $value]);
    }

    public function whereYear($column, $value): self
    {
        return $this->addStep('whereYear', [$column, $value]);
    }

    public function whereDay($column, $value): self
    {
        return $this->addStep('whereDay', [$column, $value]);
    }

    public function orWhereNull($column): self
    {
        return $this->addStep('orWhereNull', [$column]);
    }

    public function orWhereNotNull($column): self
    {
        return $this->addStep('orWhereNotNull', [$column]);
    }

    public function orWhereIn($column, $values): self
    {
        return $this->addStep('orWhereIn', [$column, $values]);
    }

    public function orWhereNotIn($column, $values): self
    {
        return $this->addStep('orWhereNotIn', [$column, $values]);
    }


    /**
     * Add a "where column" clause to the query.
     * Example: whereColumn('foo', 'bar') or whereColumn([['foo', '>', 'bar']])
     *
     * @param mixed $first
     * @param mixed|null $operatorOrSecond
     * @param mixed|null $second
     * @return WhereClauses|PluginQueryBuilder
     */
    public function whereColumn(mixed $first, mixed $operatorOrSecond = null, mixed $second = null): self
    {
        $args = func_get_args();
        return $this->addStep('whereColumn', $args);
    }

    /**
     * Add a "where JSON contains" clause to the query.
     * Example: whereJsonContains('meta->tags', 'laravel')
     *
     * @param string $column
     * @param mixed $value
     * @return WhereClauses|PluginQueryBuilder
     */
    public function whereJsonContains(string $column, mixed $value): self
    {
        return $this->addStep('whereJsonContains', [$column, $value]);
    }

    /**
     * Add a "where JSON length" clause to the query.
     * Example: whereJsonLength('options->items', '>', 3)
     *
     * @param string $column
     * @param int|string $operatorOrLength
     * @param null $length
     * @return WhereClauses|PluginQueryBuilder
     */
    public function whereJsonLength(string $column, int|string $operatorOrLength, $length = null): self
    {
        $args = func_get_args();
        return $this->addStep('whereJsonLength', $args);
    }

    /**
     * Add a "where date between" clause to the query.
     *
     * @param string $column
     * @param array $dates
     * @return WhereClauses|PluginQueryBuilder
     */
    public function whereDateBetween(string $column, array $dates): self
    {
        return $this->addStep('whereBetween', [$column, $dates]);
    }

    /**
     * Add a raw where clause to the query. (Use with extreme caution!)
     *
     * @param string $sql
     * @param array $bindings
     * @return WhereClauses|PluginQueryBuilder
     */
    public function whereRaw(string $sql, array $bindings = []): self
    {
        $this->ensureAllPermissions(['select', 'update', 'delete', 'insert']);
        return $this->addStep('whereRaw', [$sql, $bindings]);
    }

    /**
     * Add a "where exists" subquery to the query.
     * NOTE: Only enable this for trusted plugins, as subqueries can be abused.
     *
     * @param Closure $callback The subquery builder.
     * @return WhereClauses|PluginQueryBuilder
     */
    public function whereExists(Closure $callback): self
    {
        // Always wrap plugin callback for safety if you allow this
        $callback = $this->wrapCallbackWithDbMethods($this->modelAlias, $callback);
        return $this->addStep('whereExists', [$callback]);
    }

    /**
     * Add a "where not exists" subquery to the query.
     *
     * @param Closure $callback The subquery builder.
     * @return WhereClauses|PluginQueryBuilder
     */
    public function whereNotExists(Closure $callback): self
    {
        $callback = $this->wrapCallbackWithDbMethods($this->modelAlias, $callback);
        return $this->addStep('whereNotExists', [$callback]);
    }

    /**
     * Add a "where LIKE" clause to the query.
     *
     * @param string|array $columns
     * @param string $value
     * @return WhereClauses|PluginQueryBuilder
     */
    public function whereLike(string|array $columns, string $value): self
    {
        return $this->addStep('whereLike', [$columns, $value]);
    }

    /**
     * Add an "or where LIKE" clause to the query.
     *
     * @param string|array $columns
     * @param string $value
     * @return WhereClauses|PluginQueryBuilder
     */
    public function orWhereLike(string|array $columns, string $value): self
    {
        return $this->addStep('orWhereLike', [$columns, $value]);
    }

    /**
     * Add a "where FULLTEXT" clause to the query.
     * @see https://laravel.com/docs/12.x/queries#full-text-where-clauses
     *
     * @param string|array $columns
     * @param string $value
     * @param array $options (optional, e.g., ['mode' => 'boolean'])
     * @return WhereClauses|PluginQueryBuilder
     */
    public function whereFullText(string|array $columns, string $value, array $options = []): self
    {
        return $this->addStep('whereFullText', [$columns, $value, $options]);
    }

    /**
     * Add an "or where FULLTEXT" clause to the query.
     * @param string|array $columns
     * @param string $value
     * @param array $options (optional)
     * @return WhereClauses|PluginQueryBuilder
     */
    public function orWhereFullText(string|array $columns, string $value, array $options = []): self
    {
        return $this->addStep('orWhereFullText', [$columns, $value, $options]);
    }
}