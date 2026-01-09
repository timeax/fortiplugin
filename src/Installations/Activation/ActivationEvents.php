<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Activation;

/**
 * Machine-stable dotted event keys for the activation package.
 * Prefix: activation.*
 */
final class ActivationEvents
{
    // — Run Lifecycle
    public const RUN_START             = 'activation.run.start';
    public const RUN_END               = 'activation.run.end';
    public const RUN_FAIL              = 'activation.run.fail';

    // — Lock
    public const LOCK_FAIL             = 'activation.lock.fail';

    // — Validation
    public const VALIDATION_PRECHECK_OK    = 'activation.validation.precheck_ok';
    public const VALIDATION_PRECHECK_FAIL  = 'activation.validation.precheck_fail';

    // — Install Log
    public const INSTALL_LOG_READ_START = 'activation.install_log.read_start';
    public const INSTALL_LOG_READ_OK    = 'activation.install_log.read_ok';
    public const INSTALL_LOG_READ_FAIL  = 'activation.install_log.read_fail';

    // — Registries
    public const REGISTRIES_STAGE_START  = 'activation.registries.stage_start';
    public const REGISTRIES_STAGE_OK     = 'activation.registries.stage_ok';
    public const REGISTRIES_COMMIT_START = 'activation.registries.commit_start';
    public const REGISTRIES_COMMIT_OK    = 'activation.registries.commit_ok';
    public const REGISTRIES_ROLLBACK     = 'activation.registries.rollback';

    // — Database Transaction
    public const DB_TX_START             = 'activation.db.transaction_start';
    public const DB_TX_COMMIT_OK         = 'activation.db.commit_ok';
    public const DB_TX_ROLLBACK          = 'activation.db.rollback';

    // — Cache
    public const CACHE_CLEAR_START       = 'activation.cache.clear_start';
    public const CACHE_CLEAR_DONE        = 'activation.cache.clear_done';

    // — No-op
    public const NOOP                    = 'activation.noop';
}
