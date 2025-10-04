<?php

namespace Timeax\FortiPlugin\Lib\Db\Traits;

use Closure;
use Timeax\FortiPlugin\Lib\Db\PluginQueryBuilder;

trait Relationships
{
    /**
     * Eager load relationships for the query.
     *
     * Supports all Eloquent usage styles:
     * - with('relation', $callback): Loads a single relation with a constraint callback.
     * - with(['relation1' => $callback1, 'relation2', 'relation3' => $callback2, ...]): Loads multiple relations, each with or without a constraint callback.
     * - with('relation1', 'relation2', ...): Loads multiple relations without constraints.
     *
     * All callbacks are automatically wrapped for security, so plugins can only access safe APIs inside their constraint closures.
     *
     * @param mixed ...$relations One or more relationship names, or a single array mapping names to constraint callbacks.
     * @return Relationships|PluginQueryBuilder
     */
    public function with(...$relations): self
    {
        // Case 1: with('relation', $callback)
        if (count($relations) === 2 && $relations[1] instanceof Closure && is_string($relations[0])) {
            $this->checkRelationListPermissions([$relations[0]]);
            $alias = $this->resolveRelationAlias($relations[0]);
            $callback = $this->wrapCallbackWithDbMethods($alias, $relations[1]);
            $this->addStep('with', [$relations[0], $callback]);
            return $this;
        }

        // Case 2: with(['posts' => $cb, 'profile', ...])
        if (count($relations) === 1 && is_array($relations[0])) {
            $relationArray = $relations[0];
            // Check permissions for all relations requested (array keys)
            $this->checkRelationListPermissions(array_keys($relationArray));
            foreach ($relationArray as $key => $value) {
                // If key is string and value is a closure, wrap with resolved alias
                if ($value instanceof Closure) {
                    $alias = $this->resolveRelationAlias($key);
                    $relationArray[$key] = $this->wrapCallbackWithDbMethods($alias, $value);
                }
            }
            $this->addStep('with', [$relationArray]);
            return $this;
        }

        // Case 3: with('relation1', 'relation2', ...)
        $this->checkRelationListPermissions($relations);
        $this->addStep('with', [$relations]);
        return $this;
    }

    /**
     * Add relationship count attributes to the results.
     *
     * Supports all Eloquent usage styles:
     * - withCount('relation'): Count a single relation.
     * - withCount('relation1', 'relation2'): Count multiple relations.
     * - withCount(['relation1', 'relation2']): Count multiple relations (array style).
     * - withCount(['relation' => $callback]): Count a relation with a constraint callback.
     * - withCount(['relation1' => $cb, 'relation2']): Any mix of constrained/unconstrained relations.
     *
     * Any constraint callbacks will be wrapped securely so plugins receive only safe query APIs.
     *
     * @param mixed ...$relations One or more relation names, or a single array mapping names to constraint callbacks.
     * @return Relationships|PluginQueryBuilder
     */
    public function withCount(...$relations): self
    {
        if (count($relations) === 1 && is_array($relations[0])) {
            $relationArray = $relations[0];
            $this->checkRelationListPermissions(array_keys($relationArray));

            foreach ($relationArray as $relationName => $callback) {
                if ($callback instanceof Closure) {
                    // Get the correct alias for the relationship from your config:
                    $alias = $this->resolveRelationAlias($relationName); // Utility you define
                    // Wrap the callback for the *related* model, using the resolved alias:
                    $relationArray[$relationName] = $this->wrapCallbackWithDbMethods($alias, $callback);
                }
            }
            $this->addStep('withCount', [$relationArray]);
            return $this;
        }

        $this->checkRelationListPermissions($relations);

        $this->addStep('withCount', [$relations]);
        return $this;
    }

    public function withSum($relation, $column): self
    {
        return $this->addStep('withSum', [$relation, $column]);
    }

    public function withAvg($relation, $column): self
    {
        return $this->addStep('withAvg', [$relation, $column]);
    }

    public function withMax($relation, $column): self
    {
        return $this->addStep('withMax', [$relation, $column]);
    }

    public function withMin($relation, $column): self
    {
        return $this->addStep('withMin', [$relation, $column]);
    }

    /**
     * Add a "has" relationship clause to the query.
     *
     * Supports:
     * - has('relation')
     * - has('relation', '>=', 2)
     * - has('relation', $callback)
     * - has('relation', $callback, '>=', 2)
     *
     * @param string $relation
     * @param mixed ...$args
     * @return Relationships|PluginQueryBuilder
     */
    public function has(string $relation, ...$args): self
    {
        // Always check permission for the root relation
        $this->checkRelationListPermissions([$relation]);

        if (!empty($args) && $args[0] instanceof Closure) {
            // Wrap callback with correct alias
            $alias = $this->resolveRelationAlias($relation);
            $args[0] = $this->wrapCallbackWithDbMethods($alias, $args[0]);
        }
        $this->addStep('has', array_merge([$relation], $args));
        return $this;
    }

    /**
     * Add an "or has" relationship clause to the query.
     *
     * Supports:
     * - orHas('relation')
     * - orHas('relation', '>=', 2)
     * - orHas('relation', $callback)
     * - orHas('relation', $callback, '>=', 2)
     *
     * @param string $relation
     * @param mixed ...$args
     * @return Relationships|PluginQueryBuilder
     */
    public function orHas(string $relation, ...$args): self
    {
        $this->checkRelationListPermissions([$relation]);

        if (!empty($args) && $args[0] instanceof Closure) {
            $alias = $this->resolveRelationAlias($relation);
            $args[0] = $this->wrapCallbackWithDbMethods($alias, $args[0]);
        }

        $this->addStep('orHas', array_merge([$relation], $args));
        return $this;
    }

    /**
     * Add a "doesntHave" relationship clause to the query.
     *
     * @param string $relation
     * @param mixed ...$args
     * @return Relationships|PluginQueryBuilder
     */
    public function doesntHave(string $relation, ...$args): self
    {
        $this->checkRelationListPermissions([$relation]);

        if (!empty($args) && $args[0] instanceof Closure) {
            $alias = $this->resolveRelationAlias($relation);
            $args[0] = $this->wrapCallbackWithDbMethods($alias, $args[0]);
        }

        $this->addStep('doesntHave', array_merge([$relation], $args));
        return $this;
    }

    /**
     * Add a "whereHas" relationship clause to the query.
     *
     * @param string $relation
     * @param mixed ...$args
     * @return Relationships|PluginQueryBuilder
     */
    public function whereHas(string $relation, ...$args): self
    {
        $this->checkRelationListPermissions([$relation]);

        if (!empty($args) && $args[0] instanceof Closure) {
            $alias = $this->resolveRelationAlias($relation);
            $args[0] = $this->wrapCallbackWithDbMethods($alias, $args[0]);
        }

        $this->addStep('whereHas', array_merge([$relation], $args));
        return $this;
    }

    /**
     * Add a "orWhereHas" relationship clause to the query.
     *
     * @param string $relation
     * @param mixed ...$args
     * @return Relationships|PluginQueryBuilder
     */
    public function orWhereHas(string $relation, ...$args): self
    {
        $this->checkRelationListPermissions([$relation]);

        if (!empty($args) && $args[0] instanceof Closure) {
            $alias = $this->resolveRelationAlias($relation);
            $args[0] = $this->wrapCallbackWithDbMethods($alias, $args[0]);
        }

        $this->addStep('orWhereHas', array_merge([$relation], $args));
        return $this;
    }

    /**
     * Add a "whereDoesntHave" relationship clause to the query.
     *
     * @param string $relation
     * @param mixed ...$args
     * @return Relationships|PluginQueryBuilder
     */
    public function whereDoesntHave(string $relation, ...$args): self
    {
        $this->checkRelationListPermissions([$relation]);

        if (!empty($args) && $args[0] instanceof Closure) {
            $alias = $this->resolveRelationAlias($relation);
            $args[0] = $this->wrapCallbackWithDbMethods($alias, $args[0]);
        }

        $this->addStep('whereDoesntHave', array_merge([$relation], $args));
        return $this;
    }

}