<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Contracts;

/**
 * Evaluates rule/assignment conditions against a runtime context.
 * Conditions could include guard/env/setting_link; shape is up to the ingestor.
 */
interface ConditionsEvaluatorInterface
{
    /**
     * @param array $conditions e.g., ['guard'=>'api','env'=>['allow'=>['staging']],'setting_link'=>'enable_codec']
     * @param array $context    e.g., ['guard'=>'api','env'=>'staging','settings'=>['enable_codec'=>true]]
     * @return bool true if conditions pass (or no conditions)
     */
    public function matches(array $conditions, array $context): bool;
}