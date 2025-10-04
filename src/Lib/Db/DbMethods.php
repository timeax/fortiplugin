<?php

namespace Timeax\FortiPlugin\Lib\Db;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use JsonException;

class DbMethods extends PluginDbExecutor
{
    /**
     * Get all results for the query.
     *
     * @param mixed ...$args
     * @return array|Collection
     * @throws JsonException
     */
    public function get(...$args): Collection|array
    {
        return $this->execute('get', $args);
    }

    /**
     * Get the first result for the query.
     *
     * @param mixed ...$args
     * @return array|Model|null
     * @throws JsonException
     */
    public function first(...$args): Model|array|null
    {
        return $this->execute('first', $args);
    }

    /**
     * Get the first result or throw ModelNotFoundException.
     *
     * @param mixed ...$args
     * @return array|Model
     * @throws ModelNotFoundException
     * @throws JsonException
     */
    public function firstOrFail(...$args): Model|array
    {
        return $this->execute('firstOrFail', $args);
    }

    /**
     * Find a record by its primary key.
     *
     * @param mixed ...$args
     * @return array|Model|null
     * @throws JsonException
     */
    public function find(...$args): Model|array|null
    {
        return $this->execute('find', $args);
    }

    /**
     * Find a record by primary key or throw ModelNotFoundException.
     *
     * @param mixed ...$args
     * @return array|Model
     * @throws ModelNotFoundException
     * @throws JsonException
     */
    public function findOrFail(...$args): Model|array
    {
        return $this->execute('findOrFail', $args);
    }

    /**
     * Find multiple records by primary keys.
     *
     * @param mixed ...$args
     * @return array|Collection
     * @throws JsonException
     */
    public function findMany(...$args): Collection|array
    {
        return $this->execute('findMany', $args);
    }

    /**
     * Get the value of a single column.
     *
     * @param mixed ...$args
     * @return mixed
     * @throws JsonException
     */
    public function value(...$args): mixed
    {
        return $this->execute('value', $args);
    }

    /**
     * Pluck values of a single column.
     *
     * @param mixed ...$args
     * @return array
     * @throws JsonException
     */
    public function pluck(...$args): array
    {
        return $this->execute('pluck', $args);
    }

    /**
     * Get the sole result or throw exceptions.
     *
     * @param mixed ...$args
     * @return array|Model
     * @throws JsonException
     */
    public function sole(...$args): Model|array
    {
        return $this->execute('sole', $args);
    }

    /**
     * Paginate the results.
     *
     * @param mixed ...$args
     * @return LengthAwarePaginator
     * @throws JsonException
     */
    public function paginate(...$args): LengthAwarePaginator
    {
        return $this->execute('paginate', $args);
    }

    /**
     * Paginate the results with simple pagination.
     *
     * @param mixed ...$args
     * @return Paginator
     * @throws JsonException
     */
    public function simplePaginate(...$args): Paginator
    {
        return $this->execute('simplePaginate', $args);
    }

    /**
     * Determine if any records exist.
     *
     * @param mixed ...$args
     * @return bool
     * @throws JsonException
     */
    public function exists(...$args): bool
    {
        return $this->execute('exists', $args);
    }

    /**
     * Get the count of matching records.
     *
     * @param mixed ...$args
     * @return int
     * @throws JsonException
     */
    public function count(...$args): int
    {
        return $this->execute('count', $args);
    }

    /**
     * Get the maximum value of a column.
     *
     * @param mixed ...$args
     * @return mixed
     * @throws JsonException
     */
    public function max(...$args): mixed
    {
        return $this->execute('max', $args);
    }

    /**
     * Get the minimum value of a column.
     *
     * @param mixed ...$args
     * @return mixed
     * @throws JsonException
     */
    public function min(...$args): mixed
    {
        return $this->execute('min', $args);
    }

    /**
     * Get the average value of a column.
     *
     * @param mixed ...$args
     * @return mixed
     * @throws JsonException
     */
    public function avg(...$args): mixed
    {
        return $this->execute('avg', $args);
    }

    /**
     * Get the sum of a column.
     *
     * @param mixed ...$args
     * @return mixed
     * @throws JsonException
     */
    public function sum(...$args): mixed
    {
        return $this->execute('sum', $args);
    }

    /**
     * Create a new record.
     *
     * @param mixed ...$args
     * @return Model|array
     * @throws JsonException
     */
    public function create(...$args): Model|array
    {
        return $this->execute('create', $args);
    }

    /**
     * Insert records.
     *
     * @param mixed ...$args
     * @return bool
     * @throws JsonException
     */
    public function insert(...$args): bool
    {
        return $this->execute('insert', $args);
    }

    /**
     * Update matching records.
     *
     * @param mixed ...$args
     * @return int
     * @throws JsonException
     */
    public function update(...$args): int
    {
        return $this->execute('update', $args);
    }

    /**
     * Update or create a record.
     *
     * @param mixed ...$args
     * @return Model|array
     * @throws JsonException
     */
    public function updateOrCreate(...$args): Model|array
    {
        return $this->execute('updateOrCreate', $args);
    }

    /**
     * Find the first record matching attributes or create it.
     *
     * @param mixed ...$args
     * @return Model|array
     * @throws JsonException
     */
    public function firstOrCreate(...$args): Model|array
    {
        return $this->execute('firstOrCreate', $args);
    }

    /**
     * Get the first record or return a new instance.
     *
     * @param mixed ...$args
     * @return Model|array
     * @throws JsonException
     */
    public function firstOrNew(...$args): Model|array
    {
        return $this->execute('firstOrNew', $args);
    }

    /**
     * Increment a column value.
     *
     * @param mixed ...$args
     * @return int
     * @throws JsonException
     */
    public function increment(...$args): int
    {
        return $this->execute('increment', $args);
    }

    /**
     * Decrement a column value.
     *
     * @param mixed ...$args
     * @return int
     * @throws JsonException
     */
    public function decrement(...$args): int
    {
        return $this->execute('decrement', $args);
    }

    /**
     * Upsert records in the database.
     *
     * @param mixed ...$args
     * @return int
     * @throws JsonException
     */
    public function upsert(...$args): int
    {
        return $this->execute('upsert', $args);
    }

    /**
     * Delete records.
     *
     * @param mixed ...$args
     * @return int
     * @throws JsonException
     */
    public function delete(...$args): int
    {
        return $this->execute('delete', $args);
    }

    /**
     * Destroy records by primary keys.
     *
     * @param mixed ...$args
     * @return int
     * @throws JsonException
     */
    public function destroy(...$args): int
    {
        return $this->execute('destroy', $args);
    }

    /**
     * Refresh a model instance from the database.
     *
     * @param mixed ...$args
     * @return Model
     * @throws JsonException
     */
    public function refresh(...$args): Model
    {
        return $this->execute('refresh', $args);
    }

    /**
     * Truncate the table.
     *
     * @param mixed ...$args
     * @return mixed
     * @throws JsonException
     */
    public function truncate(...$args): mixed
    {
        return $this->execute('truncate', $args);
    }

    /**
     * Touch (update timestamps) on the model instance.
     *
     * @param mixed ...$args
     * @return bool
     * @throws JsonException
     */
    public function touch(...$args): bool
    {
        return $this->execute('touch', $args);
    }

    /**
     * Permanently delete records (force delete).
     *
     * @param mixed ...$args
     * @return int
     * @throws JsonException
     */
    public function forceDelete(...$args): int
    {
        return $this->execute('forceDelete', $args);
    }

    /**
     * Restore soft-deleted records.
     *
     * @param mixed ...$args
     * @return int
     * @throws JsonException
     */
    public function restore(...$args): int
    {
        return $this->execute('restore', $args);
    }

    /**
     * Get the underlying builder instance (trusted plugins only).
     *
     * @param mixed ...$args
     * @return Builder
     * @throws JsonException
     */
    public function instance(...$args): Builder
    {
        return $this->execute('instance', $args);
    }

    /**
     * Process records in chunks with a safe plugin callback.
     *
     * @param mixed ...$args
     * @return bool
     * @throws JsonException
     */
    public function chunk(...$args): bool
    {
        return $this->execute('chunk', $args);
    }
}