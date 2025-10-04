<?php

namespace Timeax\FortiPlugin\Lib\Db\Traits;
use Timeax\FortiPlugin\Lib\Db\PluginQueryBuilder;

/**
 * Trait SoftDeleteQueryTrait
 * Adds Eloquent's soft delete query helpers to your query builder.
 */
trait SoftDeleteQueryTrait
{
    /**
     * Include soft deleted models in the query results.
     *
     * @return SoftDeleteQueryTrait|PluginQueryBuilder
     */
    public function withTrashed(): self
    {
        return $this->addStep('withTrashed', []);
    }

    /**
     * Only return soft deleted models.
     *
     * @return SoftDeleteQueryTrait|PluginQueryBuilder
     */
    public function onlyTrashed(): self
    {
        return $this->addStep('onlyTrashed', []);
    }

    /**
     * Exclude soft deleted models from the results (default).
     *
     * @return SoftDeleteQueryTrait|PluginQueryBuilder
     */
    public function withoutTrashed(): self
    {
        return $this->addStep('withoutTrashed', []);
    }
}