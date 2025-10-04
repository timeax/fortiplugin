<?php

namespace Timeax\FortiPlugin\Lib\Db;

use Closure;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;
use Timeax\FortiPlugin\Core\ChecksModulePermission;

class DbGate
{
    use ChecksModulePermission;

    protected static ?array $modelMap = null;
    protected static ?string $baseNamespace = null;

    /**
     * Loads/refreshes the host config for aliases and namespace.
     */
    protected static function loadConfig(): void
    {
        // Only load once per request
        if (self::$modelMap !== null) return;

        $config = config('plugin.models', []);
        self::$modelMap = $config;
        self::$baseNamespace = config('plugin.model_base_namespace', 'App\\Models');
    }

    /**
     * Resolves a model alias and returns a strict proxy.
     */
    public static function model(string $alias): DbMethods
    {
        self::loadConfig();

        if (!isset(self::$modelMap[$alias])) {
            throw new InvalidArgumentException("Model alias '$alias' is not registered.");
        }

        $meta = self::$modelMap[$alias];
        $class = $meta['class'] ??
            (self::$baseNamespace . '\\' . ($meta['name'] ?? $alias));

        if (!class_exists($class)) {
            throw new InvalidArgumentException("Resolved class '$class' for alias '$alias' does not exist.");
        }

        return new DbMethods($alias, $class, $meta['table'] ?? null);
    }

    /**
     * Begin a database transaction and execute the callback atomically.
     *
     * Example:
     *   DbGate::transaction(function () {
     *      DbGate::model('user')->create([...]);
     *   });
     *
     * @param Closure $callback
     * @param int $attempts
     * @return mixed
     * @throws Throwable
     */
    public static function transaction(Closure $callback, int $attempts = 1): mixed
    {
        return DB::transaction($callback, $attempts);
    }

    /**
     * Begin a manual database transaction (classic style).
     *
     * Example:
     *   DbGate::beginTransaction();
     *   // ...do work
     *   DbGate::commit();
     * @throws Throwable
     */
    public static function beginTransaction(): void
    {
        DB::beginTransaction();
    }

    /**
     * Commit the current transaction.
     * @throws Throwable
     */
    public static function commit(): void
    {
        DB::commit();
    }

    /**
     * Rollback the current transaction.
     * @throws Throwable
     */
    public static function rollback(): void
    {
        DB::rollBack();
    }
}