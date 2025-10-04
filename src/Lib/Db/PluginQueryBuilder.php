<?php

namespace Timeax\FortiPlugin\Lib\Db;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use LogicException;
use Timeax\FortiPlugin\Exceptions\PermissionDeniedException;
use Timeax\FortiPlugin\Models\PluginDbPermission;
use Timeax\FortiPlugin\Lib\Db\Traits\OrderingGroupingLimitTrait;
use Timeax\FortiPlugin\Lib\Db\Traits\Relationships;
use Timeax\FortiPlugin\Lib\Db\Traits\SoftDeleteQueryTrait;
use Timeax\FortiPlugin\Lib\Db\Traits\WhereClauses;
use Timeax\FortiPlugin\Core\ChecksModulePermission;

/**
 * PluginQueryBuilder
 *
 * Collects and stores chainable query steps.
 * Does not execute queries or return results.
 * Used as the base for executor and public API classes.
 */
class PluginQueryBuilder
{
    use ChecksModulePermission, WhereClauses, OrderingGroupingLimitTrait, SoftDeleteQueryTrait, Relationships;

    protected string $modelAlias;

    /**
     * @var Builder|class-string<Model>|Model
     */
    protected string|Model|Builder $modelClass;
    protected ?string $table;
    protected ?array $context;
    protected array $querySteps = [];
    private string $type = 'db';
    protected string $target;

    protected ?array $readableFields = null;
    protected ?array $hiddenFields = null;
    protected ?array $writableFields = null;

    public function __construct(string $modelAlias, string|Model|Builder $modelClass, ?string $table = null, ?array $context = [])
    {
        $this->modelAlias = $modelAlias;
        $this->modelClass = $modelClass;
        $this->table = $table;
        $this->target = $modelAlias;
        $this->context = $context;

        $this->ensurePermission('select');

        // Just load the permission row for this model
        $dbPerm = PluginDbPermission::where('model', $modelAlias)->first();

        $this->readableFields = $dbPerm?->readable_fields ?? null;
        $this->writableFields = $dbPerm?->writable_fields ?? null;
        $this->hiddenFields = $dbPerm?->hidden_fields ?? null;
    }

    protected array $checkedPerms = [];

    /** Permission cache-aware checker */
    protected function ensurePermission(string $action): void
    {
        if (!isset($this->checkedPerms[$action])) {
            // Will throw if not allowed; if returns, it's safe
            $this->checkModulePermission($action, $this->type, $this->target);
            $this->checkedPerms[$action] = true;
        }
        // else, already confirmed for this action+model on this instance
    }

    protected function ensurePermissionForAlias(string $action, string $alias): void
    {
        // If the alias is the same as $this->target, just use ensurePermission
        if ($alias === $this->target) {
            $this->ensurePermission($action);
        } else {
            // Use your trait, but override the $target param
            $this->checkModulePermission($action, $this->type, $alias);
            $this->checkedPerms["$action:$alias"] = true;
        }
    }

    protected function ensureAllPermissions(array $actions): void
    {
        foreach ($actions as $action) {
            $this->ensurePermission($action);
        }
    }

    /**
     * Resolve and check permissions for all segments of a (possibly nested) relation string.
     * e.g., 'posts.comments', 'posts:id,title', etc.
     *
     * @param string $relation The (possibly nested, possibly data-selected) relation
     * @throws InvalidArgumentException If any relation is not allowed
     */
    protected function checkNestedRelationPermissions(string $relation): void
    {
        $segments = explode('.', $relation);
        $currentAlias = $this->target;

        foreach ($segments as $segment) {
            // Strip any data selector (e.g., posts:id,title → posts)
            [$segmentName] = explode(':', $segment, 2);

            // Look up alias from config
            $modelConfig = config('plugin.models.' . $currentAlias);
            $relations = $modelConfig['relations'] ?? [];

            if (!isset($relations[$segmentName])) {
                throw new InvalidArgumentException("Relationship '$segmentName' is not allowed for model '$currentAlias'.");
            }

            $nextAlias = $relations[$segmentName];

            // Check permission for select on this related model
            $this->ensurePermissionForAlias('select', $nextAlias);

            // Step into the next model for further segments
            $currentAlias = $nextAlias;
        }
    }

    /**
     * Add a query step.
     * @param string $type
     * @param array $args
     * @return $this
     */
    protected function addStep(string $type, array $args): self
    {
        $this->querySteps[] = ['type' => $type, 'args' => $args];
        return $this;
    }

    /**
     * Wrap a plugin batch callback to ensure each item is given as DbMethods or array, never as Model/Builder.
     *
     * @param string $alias
     * @param Closure $pluginCallback
     * @param array $context
     * @return Closure
     */
    protected function wrapBatchCallbackWithDbMethods(string $alias, Closure $pluginCallback, array $context = []): Closure
    {
        return static function ($collection) use ($alias, $pluginCallback, $context) {
            foreach ($collection as $item) {
                // Decide what to wrap (Model, Builder, or fallback to array)
                if ($item instanceof Model || $item instanceof Builder) {
                    $safeItem = new DbMethods($alias, $item, null, $context);
                } else {
                    // For arrays/scalars, just pass as-is
                    $safeItem = $item;
                }
                $pluginCallback($safeItem);
            }
        };
    }

    /**
     * Utility to wrap a plugin callback with a safe DbMethods API.
     * Returns a closure suitable for Eloquent's relationship or batch methods.
     *
     * @param string $alias
     * @param Closure $pluginCallback
     * @param array $context (optional, e.g. permissions, config)
     * @return Closure
     */
    protected function wrapCallbackWithDbMethods(string $alias, Closure $pluginCallback, array $context = []): Closure
    {
        return static function ($eloquentBuilderOrModel) use ($alias, $pluginCallback, $context) {
            $dbMethods = new DbMethods($alias, $eloquentBuilderOrModel, null, $context);
            $pluginCallback($dbMethods);
        };
    }
    // --------------------------
    // CHAINABLE QUERY METHODS
    // --------------------------

    /**
     * Specify columns to select. Throws if any are forbidden/hidden.
     *
     * @param array|string ...$columns
     * @return $this
     * @throws PermissionDeniedException
     */
    public function select(array|string ...$columns): self
    {
        // Support select(['a', 'b']) and select('a', 'b')
        $columns = is_array($columns) ? $columns : func_get_args();

        // Enforce field-level permissions if set
        if ($this->hiddenFields) {
            foreach ($columns as $col) {
                if (in_array($col, $this->hiddenFields, true)) {
                    $this->denyPermission(
                        "Plugin is not allowed to select hidden/forbidden column '$col' on '{$this->modelAlias}'.",
                        $this->target,
                        'select'
                    );
                }
            }
        }

        return $this->addStep('select', [$columns]);
    }

    public function distinct(): self
    {
        return $this->addStep('distinct', []);
    }

    /**
     * Order results by latest (descending) on the given column.
     * @param string $column
     * @return $this
     */
    public function latest(string $column = 'created_at'): self
    {
        return $this->addStep('latest', [$column]);
    }

    /**
     * Order results by oldest (ascending) on the given column.
     * @param string $column
     * @return $this
     */
    public function oldest(string $column = 'created_at'): self
    {
        return $this->addStep('oldest', [$column]);
    }

    /**
     * Lock rows for update.
     * @return $this
     */
    public function lockForUpdate(): self
    {
        return $this->addStep('lockForUpdate', []);
    }

    /**
     * Lock rows with a shared lock.
     * @return $this
     */
    public function sharedLock(): self
    {
        return $this->addStep('sharedLock', []);
    }

    // --------------------------
    // RELATIONSHIP/EAGER LOADING
    // --------------------------
    /**
     * Check permissions for all (possibly nested) relations in an array or single relation string.
     * Use for 'with', 'withCount', 'withSum', etc.
     *
     * @param array|string $relations
     * @throws InvalidArgumentException If any relation or segment is not allowed
     */
    protected function checkRelationListPermissions(array|string $relations): void
    {
        $relations = is_array($relations) ? $relations : [$relations];
        foreach ($relations as $relation) {
            $this->checkNestedRelationPermissions($relation);
        }
    }

    /**
     * Resolves a relation name (as used in Eloquent) to its alias as defined in your config.
     *
     * @param string $relationName
     * @return string
     */
    protected function resolveRelationAlias(string $relationName): string
    {
        // Example: config('plugin.models.' . $this->modelAlias . '.relations.' . $relationName)
        return config('plugin.models.' . $this->modelAlias . '.relations.' . $relationName, $relationName);
    }


    /**
     * Conditionally add clauses to the query using a callback if the given value is truthy.
     *
     * @param mixed $value The value to test.
     * @param Closure $callback The callback to invoke if $value is truthy.
     * @param Closure|null $default Optional: callback if $value is falsy.
     * @return $this
     */
    public function when(mixed $value, Closure $callback, Closure $default = null): self
    {
        // Wrap both callbacks for security
        $callback = $this->wrapCallbackWithDbMethods($this->modelAlias, $callback);
        if ($default !== null) {
            $default = $this->wrapCallbackWithDbMethods($this->modelAlias, $default);
        }
        return $this->addStep('when', [$value, $callback, $default]);
    }
    // --------------------------
    // UTILITY METHODS
    // --------------------------

    /**
     * Get the collected query steps (for executors/dispatchers).
     * @return array
     */
    protected function getQuerySteps(): array
    {
        return $this->querySteps;
    }

    protected function getActiveBuilder()
    {
        if ($this->modelClass instanceof Model || $this->modelClass instanceof Builder) {
            // Use the instance directly for chaining
            return $this->modelClass;
        }
        // Else, create builder from class string
        return ($this->modelClass)::query();
    }

    /**
     * Utility: reset the builder (for next query, if needed).
     * @return $this
     */
    public function reset(): self
    {
        $this->querySteps = [];
        return $this;
    }

    /**
     * Build an Eloquent builder with all stored steps (for executor).
     * @return Builder
     */
    protected function buildEloquentBuilder(): Builder
    {
        $builder = $this->getActiveBuilder();

        foreach ($this->querySteps as $step) {
            // Handle any methods that require special permission logic or argument rewriting
            switch ($step['type']) {
                case 'whereHas':
                case 'orWhereHas':
                case 'whereDoesntHave':
                case 'doesntHave':
                    // Example: Could do extra permission check or wrap callback here if needed
                    // Or just fall through to default dynamic
                    break;
                // ...add any other special cases here...
            }

            // Default: If the method exists on the builder, call it dynamically
            if (method_exists($builder, $step['type'])) {
                $builder->{$step['type']}(...$step['args']);
            } else {
                throw new LogicException(
                    "Query step type '{$step['type']}' is not implemented in buildEloquentBuilder()."
                );
            }
        }

        return $builder;
    }
}