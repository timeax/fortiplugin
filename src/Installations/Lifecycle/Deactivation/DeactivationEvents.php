<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Lifecycle\Deactivation;

final class DeactivationEvents
{
    public const RUN_START = 'deactivation.run.start';
    public const RUN_END = 'deactivation.run.end';
    public const RUN_FAIL = 'deactivation.run.fail';

    public const LOCK_FAIL = 'deactivation.lock.fail';
    public const NOOP = 'deactivation.noop';

    public const REGISTRIES_STAGE_START = 'deactivation.registries.stage.start';
    public const REGISTRIES_STAGE_OK = 'deactivation.registries.stage.ok';
    public const REGISTRIES_ROLLBACK = 'deactivation.registries.rollback';

    public const DB_TX_START = 'deactivation.db.tx.start';
    public const DB_TX_COMMIT_OK = 'deactivation.db.tx.commit.ok';
    public const DB_TX_ROLLBACK = 'deactivation.db.tx.rollback';

    public const REGISTRIES_COMMIT_START = 'deactivation.registries.commit.start';
    public const REGISTRIES_COMMIT_OK = 'deactivation.registries.commit.ok';

    public const CACHE_CLEAR_START = 'deactivation.cache.clear.start';
    public const CACHE_CLEAR_DONE = 'deactivation.cache.clear.done';
}