<?php

namespace Timeax\FortiPlugin\Lib\Db;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use JsonException;
use LogicException;
use Throwable;
use Timeax\FortiPlugin\Exceptions\PermissionDeniedException;

class PluginDbExecutor extends PluginQueryBuilder
{

    /**
     * Remove hidden/forbidden fields from one model or a collection.
     *
     * @param \Illuminate\Support\Collection|Model|null $result
     * @return mixed
     */
    protected function removeHiddenFields(Model|\Illuminate\Support\Collection|null $result): mixed
    {
        if (!$this->hiddenFields) {
            return $result;
        }

        if ($result instanceof \Illuminate\Support\Collection) {
            foreach ($result as $model) {
                foreach ($this->hiddenFields as $field) {
                    unset($model->$field);
                }
            }
        } elseif ($result instanceof Model) {
            foreach ($this->hiddenFields as $field) {
                unset($result->$field);
            }
        }

        return $result;
    }

    /**
     * Determine if the plugin has both update and delete permissions.
     *
     * @return bool
     */
    protected function canMutateModel(): bool
    {
        return $this->can('update') && $this->can('delete');
    }

    /**
     * Helper: Checks if the plugin has a specific action permission on this model.
     * @param string $action
     * @return bool
     */
    protected function can(string $action): bool
    {
        try {
            $this->ensurePermission($action);
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Given a result, only return Eloquent Model if plugin can mutate (update & delete).
     * Otherwise, return as array or null.
     *
     * @param mixed $result
     * @return Model|null
     * @throws JsonException
     */
    protected function restrictModelReturn(mixed $result): Model|null
    {
        if ($result instanceof Model && !$this->canMutateModel()) {
            $array = $result->toArray();
            // Only convert if toArray actually returned an array (should always, but check for sanity)
            return is_array($array) ? $this->arrayToObject($array) : $array;
        }
        return $result;
    }

    /**
     * Recursively convert an array to an object.
     *
     * @param array $array
     * @return object
     * @throws JsonException
     */
    protected function arrayToObject(array $array): object
    {
        return json_decode(json_encode($array, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Execute the built query and return all results as a collection.
     * If columns are specified, ensure none are forbidden/hidden.
     *
     * @param Builder $builder
     * @param array $args
     * @return mixed
     * @throws PermissionDeniedException
     */
    protected function executeGet(Builder $builder, array $args = []): mixed
    {
        $this->ensurePermission('select');

        // Explicit select check...
        if (!empty($args) && is_array($args[0])) {
            $columns = $args[0];
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
        }

        $results = $builder->get(...$args);

        return $this->removeHiddenFields($results);
    }

    /**
     * Execute the query and return the first result.
     *
     * @param $builder
     * @param array $args
     * @return Model|array|null
     * @throws JsonException
     */
    protected function executeFirst($builder, array $args = []): Model|array|null
    {
        $this->ensurePermission('select');

        $result = $builder->first(...$args);

        return $this->restrictModelReturn($this->removeHiddenFields($result));
    }


    /**
     * Execute the query and return the first result, or throw.
     *
     * @param $builder
     * @param array $args
     * @return Model|array|null
     * @throws JsonException
     * @throws JsonException
     */
    protected function executeFirstOrFail($builder, array $args = []): Model|array|null
    {
        $this->ensurePermission('select');

        $result = $builder->firstOrFail(...$args);
        return $this->restrictModelReturn($this->removeHiddenFields($result));
    }

    /**
     * Find a model by its primary key.
     *
     * @param $builder
     * @param array $args
     * @return Model|array|null
     * @throws JsonException
     * @throws JsonException
     */
    protected function executeFind($builder, array $args = []): Model|array|null
    {
        $this->ensurePermission('select');

        $result = $builder->find(...$args);
        return $this->restrictModelReturn($this->removeHiddenFields($result));
    }

    /**
     * Find a model by its primary key or throw.
     *
     * @param $builder
     * @param array $args
     * @return Model|array|null
     * @throws JsonException
     * @throws JsonException
     */
    protected function executeFindOrFail($builder, array $args = []): Model|array|null
    {
        $this->ensurePermission('select');

        $result = $builder->findOrFail(...$args);
        return $this->restrictModelReturn($this->removeHiddenFields($result));
    }

    /**
     * Find multiple models by their primary keys.
     *
     * @param Builder $builder
     * @param array $args
     * @return Collection|array
     */
    protected function executeFindMany(Builder $builder, array $args = []): Collection|array
    {
        $this->ensurePermission('select');

        $result = $builder->findMany(...$args);
        // Remove hidden fields for each model in the collection
        $result = $this->removeHiddenFields($result);

        // If plugin lacks mutate permissions, convert to array of arrays
        if (!$this->canMutateModel()) {
            return $result->toArray();
        }
        return $result;
    }

    /**
     * Get a single column's value from the first result of the query.
     *
     * @param $builder
     * @param array $args
     * @return mixed
     */
    protected function executeValue($builder, array $args = []): mixed
    {
        $this->ensurePermission('select');

        // Check if requesting a hidden/forbidden field
        $column = $args[0] ?? null;
        if ($this->hiddenFields && $column && in_array($column, $this->hiddenFields, true)) {
            $this->denyPermission(
                "Plugin is not allowed to access hidden/forbidden column '$column' on '{$this->modelAlias}'.",
                $this->target,
                'select'
            );
        }

        return $builder->value(...$args);
    }

    protected function executePluck($builder, array $args = [])
    {
        $this->ensurePermission('select');

        $column = $args[0] ?? null;
        if ($this->hiddenFields && $column && in_array($column, $this->hiddenFields, true)) {
            $this->denyPermission(
                "Plugin is not allowed to pluck hidden/forbidden column '$column' on '{$this->modelAlias}'.",
                $this->target,
                'select'
            );
        }

        return $builder->pluck(...$args);
    }

    /**
     * @throws JsonException
     */
    protected function executeSole($builder, array $args = []): Model|array|null
    {
        $this->ensurePermission('select');

        $result = $builder->sole(...$args);
        return $this->restrictModelReturn($this->removeHiddenFields($result));
    }

    protected function executePaginate($builder, array $args = []): LengthAwarePaginator|array
    {
        $this->ensurePermission('select');

        $results = $builder->paginate(...$args);
        // Remove hidden fields from each item in the paginator
        if ($this->hiddenFields) {
            foreach ($results as $model) {
                foreach ($this->hiddenFields as $field) {
                    unset($model->$field);
                }
            }
        }
        // If plugin lacks mutate permissions, convert paginator items to array
        if (!$this->canMutateModel()) {
            $results->getCollection()->transform(fn($model) => $model->toArray());
        }
        return $results;
    }

    /**
     * @param $builder
     * @param array $args
     * @return Paginator|array
     */
    protected function executeSimplePaginate($builder, array $args = []): Paginator|array
    {
        $this->ensurePermission('select');

        $results = $builder->simplePaginate(...$args);
        // Remove hidden fields from each item in the paginator
        if ($this->hiddenFields) {
            foreach ($results as $model) {
                foreach ($this->hiddenFields as $field) {
                    unset($model->$field);
                }
            }
        }
        // Convert to array of arrays if mutate not allowed
        if (!$this->canMutateModel()) {
            $results->getCollection()->transform(fn($model) => $model->toArray());
        }
        return $results;
    }

    /**
     * @param $builder
     * @param array $args
     * @return bool
     */
    protected function executeExists($builder, array $args = []): bool
    {
        $this->ensurePermission('select');
        return $builder->exists(...$args);
    }

    /**
     * Get the count of records for the query.
     *
     * @param Builder $builder
     * @param array $args
     * @return int
     */
    protected function executeCount(Builder $builder, array $args = []): int
    {
        $this->ensurePermission('select');
        return $builder->count(...$args);
    }

    /**
     * Get the maximum value of a column for the query.
     *
     * @param Builder $builder
     * @param array $args
     * @return mixed
     */
    protected function executeMax(Builder $builder, array $args = []): mixed
    {
        $this->ensurePermission('select');

        $column = $args[0] ?? null;
        if ($this->hiddenFields && $column && in_array($column, $this->hiddenFields, true)) {
            $this->denyPermission(
                "Plugin is not allowed to get max of hidden/forbidden column '$column' on '{$this->modelAlias}'.",
                $this->target,
                'select'
            );
        }

        return $builder->max(...$args);
    }

    /**
     * Get the minimum value of a column for the query.
     *
     * @param Builder $builder
     * @param array $args
     * @return mixed
     */
    protected function executeMin(Builder $builder, array $args = []): mixed
    {
        $this->ensurePermission('select');

        $column = $args[0] ?? null;
        if ($this->hiddenFields && $column && in_array($column, $this->hiddenFields, true)) {
            $this->denyPermission(
                "Plugin is not allowed to get min of hidden/forbidden column '$column' on '{$this->modelAlias}'.",
                $this->target,
                'select'
            );
        }

        return $builder->min(...$args);
    }

    /**
     * Get the average value of a column for the query.
     *
     * @param Builder $builder
     * @param array $args
     * @return mixed
     */
    protected function executeAvg(Builder $builder, array $args = []): mixed
    {
        $this->ensurePermission('select');

        $column = $args[0] ?? null;
        if ($this->hiddenFields && $column && in_array($column, $this->hiddenFields, true)) {
            $this->denyPermission(
                "Plugin is not allowed to get avg of hidden/forbidden column '$column' on '{$this->modelAlias}'.",
                $this->target,
                'select'
            );
        }

        return $builder->avg(...$args);
    }

    /**
     * Get the sum of a column for the query.
     *
     * @param Builder $builder
     * @param array $args
     * @return mixed
     */
    protected function executeSum(Builder $builder, array $args = []): mixed
    {
        $this->ensurePermission('select');

        $column = $args[0] ?? null;
        if ($this->hiddenFields && $column && in_array($column, $this->hiddenFields, true)) {
            $this->denyPermission(
                "Plugin is not allowed to get sum of hidden/forbidden column '$column' on '{$this->modelAlias}'.",
                $this->target,
                'select'
            );
        }

        return $builder->sum(...$args);
    }


    /**
     * Create a new record in the database.
     * Checks that only writable fields are present and none are hidden.
     *
     * @param Builder $builder
     * @param array $args
     * @return Model|array|null
     * @throws JsonException
     * @throws JsonException
     */
    protected function executeCreate(Builder $builder, array $args = []): Model|array|null
    {
        $this->ensurePermission('insert');
        $data = $args[0] ?? [];

        // Check hidden/writable fields
        $this->validateWritableFields($data);

        $model = $builder->create($data);
        $model = $this->removeHiddenFields($model);

        return $this->restrictModelReturn($model);
    }

    /**
     * Ensure all provided fields are writable and not hidden.
     *
     * @param array $data
     * @throws PermissionDeniedException
     */
    protected function validateWritableFields(array $data): void
    {
        if ($this->hiddenFields) {
            foreach (array_keys($data) as $field) {
                if (in_array($field, $this->hiddenFields, true)) {
                    $this->denyPermission(
                        "Plugin is not allowed to write hidden/forbidden column '$field' on '{$this->modelAlias}'.",
                        $this->target,
                        'insert'
                    );
                }
            }
        }
        if ($this->writableFields) {
            foreach (array_keys($data) as $field) {
                if (!in_array($field, $this->writableFields, true)) {
                    $this->denyPermission(
                        "Plugin is not allowed to write column '$field' on '{$this->modelAlias}'.",
                        $this->target,
                        'insert'
                    );
                }
            }
        }
    }

    /**
     * Insert multiple records into the database (bulk insert).
     * Checks that all fields are writable and not hidden.
     *
     * @param Builder $builder
     * @param array $args
     * @return bool
     */
    protected function executeInsert(Builder $builder, array $args = []): bool
    {
        $this->ensurePermission('insert');
        $rows = $args[0] ?? [];

        // Each row must be checked for hidden/writable fields
        foreach ($rows as $row) {
            $this->validateWritableFields($row);
        }

        return $builder->insert($rows);
    }

    /**
     * Update records matching the built query.
     * Checks that all fields are writable and not hidden.
     *
     * @param Builder $builder
     * @param array $args
     * @return int  Number of records updated
     */
    protected function executeUpdate(Builder $builder, array $args = []): int
    {
        $this->ensurePermission('update');
        $data = $args[0] ?? [];

        $this->validateWritableFields($data);

        return $builder->update($data);
    }

    /**
     * Update an existing record matching the attributes, or create it if it doesn't exist.
     * Checks that all fields are writable and not hidden.
     *
     * @param Builder $builder
     * @param array $args
     * @return Model|array|null
     * @throws JsonException
     * @throws JsonException
     */
    protected function executeUpdateOrCreate(Builder $builder, array $args = []): Model|array|null
    {
        $this->ensurePermission('update');
        $attributes = $args[0] ?? [];
        $values = $args[1] ?? [];

        // Both sets of fields must be checked
        $this->validateWritableFields($attributes);
        $this->validateWritableFields($values);

        $model = $builder->updateOrCreate($attributes, $values);
        $model = $this->removeHiddenFields($model);

        return $this->restrictModelReturn($model);
    }

    /**
     * Return the first matching record, or create it.
     * Checks that all fields are writable and not hidden.
     *
     * @param Builder $builder
     * @param array $args
     * @return Model|array|null
     * @throws JsonException
     * @throws JsonException
     */
    protected function executeFirstOrCreate(Builder $builder, array $args = []): Model|array|null
    {
        $this->ensurePermission('insert');
        $attributes = $args[0] ?? [];
        $values = $args[1] ?? [];

        $this->validateWritableFields($attributes);
        $this->validateWritableFields($values);

        $model = $builder->firstOrCreate($attributes, $values);
        $model = $this->removeHiddenFields($model);

        return $this->restrictModelReturn($model);
    }

    /**
     * Return the first matching record as a model, or instantiate a new one (not saved).
     * Checks that all fields are writable and not hidden.
     *
     * @param Builder $builder
     * @param array $args
     * @return Model|array|null
     * @throws JsonException
     * @throws JsonException
     */
    protected function executeFirstOrNew(Builder $builder, array $args = []): Model|array|null
    {
        $this->ensurePermission('insert');
        $attributes = $args[0] ?? [];
        $values = $args[1] ?? [];

        $this->validateWritableFields($attributes);
        $this->validateWritableFields($values);

        $model = $builder->firstOrNew($attributes, $values);
        $model = $this->removeHiddenFields($model);

        return $this->restrictModelReturn($model);
    }

    /**
     * Increment a column's value by a given amount.
     * Checks that the column is writable and not hidden.
     *
     * @param Builder $builder
     * @param array $args
     * @return int  Number of affected rows
     */
    protected function executeIncrement(Builder $builder, array $args = []): int
    {
        $this->ensurePermission('update');

        $column = $args[0] ?? null;
        if ($this->hiddenFields && $column && in_array($column, $this->hiddenFields, true)) {
            $this->denyPermission(
                "Plugin is not allowed to increment hidden/forbidden column '$column' on '{$this->modelAlias}'.",
                $this->target,
                'update'
            );
        }
        if ($this->writableFields && $column && !in_array($column, $this->writableFields, true)) {
            $this->denyPermission(
                "Plugin is not allowed to increment column '$column' on '{$this->modelAlias}'.",
                $this->target,
                'update'
            );
        }

        // Pass through extra data for fillable columns
        return $builder->increment(...$args);
    }

    /**
     * Decrement a column's value by a given amount.
     * Checks that the column is writable and not hidden.
     *
     * @param Builder $builder
     * @param array $args
     * @return int  Number of affected rows
     */
    protected function executeDecrement(Builder $builder, array $args = []): int
    {
        $this->ensurePermission('update');

        $column = $args[0] ?? null;
        if ($this->hiddenFields && $column && in_array($column, $this->hiddenFields, true)) {
            $this->denyPermission(
                "Plugin is not allowed to decrement hidden/forbidden column '$column' on '{$this->modelAlias}'.",
                $this->target,
                'update'
            );
        }
        if ($this->writableFields && $column && !in_array($column, $this->writableFields, true)) {
            $this->denyPermission(
                "Plugin is not allowed to decrement column '$column' on '{$this->modelAlias}'.",
                $this->target,
                'update'
            );
        }

        // Pass through extra data for fillable columns
        return $builder->decrement(...$args);
    }

    /**
     * Insert or update multiple records in a single query.
     * Checks that all fields are writable and not hidden.
     *
     * @param Builder $builder
     * @param array $args
     * @return int|bool  Number of affected rows, or true/false depending on driver
     */
    protected function executeUpsert(Builder $builder, array $args = []): bool|int
    {
        $this->ensurePermission('insert');

        $values = $args[0] ?? [];
        $uniqueBy = $args[1] ?? [];
        $update = $args[2] ?? null;

        foreach ($values as $row) {
            $this->validateWritableFields($row);
        }
        if ($update && is_array($update)) {
            $this->validateWritableFields($update);
        }

        return $builder->upsert($values, $uniqueBy, $update);
    }

    /**
     * Delete records matching the built query.
     *
     * @param Builder $builder
     * @param array $args
     * @return int  Number of deleted records
     */
    protected function executeDelete(Builder $builder, array $args = []): int
    {
        $this->ensurePermission('delete');
        return $builder->delete(...$args);
    }

    /**
     * Delete models by their primary keys.
     *
     * @param Builder $builder
     * @param array $args
     * @return int  Number of records deleted
     */
    protected function executeDestroy(Builder $builder, array $args = []): int
    {
        $this->ensurePermission('delete');
        $ids = $args[0] ?? [];
        return ($this->modelClass)::destroy($ids);
    }

    /**
     * Refresh a model instance from the database.
     * The model instance must be provided as the first argument.
     *
     * @param Builder $builder
     * @param array $args
     * @return Model
     */
    protected function executeRefresh(Builder $builder, array $args = []): Model
    {
        $this->ensurePermission('select');
        $model = $args[0] ?? null;
        if ($model instanceof Model) {
            return $model->refresh();
        }
        throw new InvalidArgumentException('No model instance given for refresh.');
    }

    /**
     * Truncate (empty) the entire table.
     *
     * @param Builder $builder
     * @param array $args
     * @return mixed
     */
    protected function executeTruncate(Builder $builder, array $args = []): mixed
    {
        $this->ensurePermission('truncate'); // Consider a special permission!

        /** @var mixed $model */
        $model = $this->modelClass;
        // Handle: class-string<Model>, Model instance, or Builder instance
        if (is_string($model)) {
            // Class name
            return $model::truncate();
        }

        if ($model instanceof Model) {
            // Model instance
            return $model->truncate();
        }

        if ($model instanceof Builder) {
            // Builder: get the model, then call truncate
            return $model->getModel()::truncate();
        }

        throw new LogicException('Cannot truncate: invalid modelClass type');
    }

    /**
     * Touch (update timestamps) on the given model instance.
     * The model instance must be provided as the first argument.
     *
     * @param Builder $builder
     * @param array $args
     * @return bool
     */
    protected function executeTouch(Builder $builder, array $args = []): bool
    {
        $this->ensurePermission('update');
        $model = $args[0] ?? null;
        if ($model instanceof Model) {
            return $model->touch();
        }
        throw new InvalidArgumentException('No model instance given for touch.');
    }

    /**
     * Permanently delete matching models, bypassing soft deletes.
     *
     * @param Builder $builder
     * @param array $args
     * @return int  Number of records deleted
     */
    protected function executeForceDelete(Builder $builder, array $args = []): int
    {
        $this->ensurePermission('delete');
        return $builder->forceDelete(...$args);
    }

    /**
     * Restore soft-deleted models matching the query.
     *
     * @param Builder $builder
     * @param array $args
     * @return int  Number of records restored
     */
    protected function executeRestore(Builder $builder, array $args = []): int
    {
        $this->ensurePermission('update');
        return $builder->restore(...$args);
    }


    /**
     * Return the underlying Eloquent builder instance.
     *
     * This method should only be used by trusted plugins and after all critical permissions
     * ('select', 'update', 'delete', 'insert', 'instance') have been validated.
     *
     * @param Builder $builder The query builder instance.
     * @param array $args (unused)
     * @return Builder
     */
    protected function executeInstance(Builder $builder, array $args = []): Builder
    {
        $this->ensureAllPermissions(['select', 'update', 'delete', 'insert', 'instance']);
        return $builder;
    }

    /**
     * Execute the query in chunks and process each chunk with the plugin callback.
     *
     * For each item in the chunked collection, the plugin callback receives a wrapped
     * DbMethods instance, never the raw model/builder. All relevant permissions are enforced.
     *
     * @param Builder $builder The query builder instance.
     * @param array $args [$count, $pluginCallback]
     *   - $count (int): Number of records per chunk (default: 100).
     *   - $pluginCallback (Closure): The plugin's callback, which will only receive safe API wrappers.
     * @return bool
     * @throws InvalidArgumentException If no valid callback is provided.
     */
    protected function executeChunk(Builder $builder, array $args = []): bool
    {
        $this->ensurePermission('select');

        $count = $args[0] ?? 100;
        $pluginCallback = $args[1] ?? null;
        if (!$pluginCallback || !is_callable($pluginCallback)) {
            throw new InvalidArgumentException('No valid callback provided to chunk().');
        }

        $alias = $this->modelAlias;
        $wrappedCallback = $this->wrapBatchCallbackWithDbMethods($alias, $pluginCallback);

        return $builder->chunk($count, $wrappedCallback);
    }

    /**
     * Execute a database action by name.
     *
     * @param string $action The action/method name (e.g., 'get', 'first', 'find', etc.)
     * @param array $args The arguments for the action (optional)
     * @return mixed
     * @throws LogicException If action is not supported
     * @throws JsonException
     */
    public function execute(string $action, array $args = []): mixed
    {
        $builder = $this->buildEloquentBuilder();

        return match ($action) {
            'get' => $this->executeGet($builder, $args),
            'first' => $this->executeFirst($builder, $args),
            'firstOrFail' => $this->executeFirstOrFail($builder, $args),
            'find' => $this->executeFind($builder, $args),
            'findOrFail' => $this->executeFindOrFail($builder, $args),
            'findMany' => $this->executeFindMany($builder, $args),
            'value' => $this->executeValue($builder, $args),
            'pluck' => $this->executePluck($builder, $args),
            'sole' => $this->executeSole($builder, $args),
            'paginate' => $this->executePaginate($builder, $args),
            'simplePaginate' => $this->executeSimplePaginate($builder, $args),
            'exists' => $this->executeExists($builder, $args),
            'count' => $this->executeCount($builder, $args),
            'max' => $this->executeMax($builder, $args),
            'min' => $this->executeMin($builder, $args),
            'avg' => $this->executeAvg($builder, $args),
            'sum' => $this->executeSum($builder, $args),
            'create' => $this->executeCreate($builder, $args),
            'insert' => $this->executeInsert($builder, $args),
            'update' => $this->executeUpdate($builder, $args),
            'updateOrCreate' => $this->executeUpdateOrCreate($builder, $args),
            'firstOrCreate' => $this->executeFirstOrCreate($builder, $args),
            'firstOrNew' => $this->executeFirstOrNew($builder, $args),
            'increment' => $this->executeIncrement($builder, $args),
            'decrement' => $this->executeDecrement($builder, $args),
            'upsert' => $this->executeUpsert($builder, $args),
            'delete' => $this->executeDelete($builder, $args),
            'destroy' => $this->executeDestroy($builder, $args),
            'refresh' => $this->executeRefresh($builder, $args),
            'truncate' => $this->executeTruncate($builder, $args),
            'touch' => $this->executeTouch($builder, $args),
            'forceDelete' => $this->executeForceDelete($builder, $args),
            'restore' => $this->executeRestore($builder, $args),
            "instance" => $this->executeInstance($builder, $args),
            "chunk" => $this->executeChunk($builder, $args),
            default => throw new LogicException("Unknown or unsupported DB action: '$action'"),
        };
    }
}