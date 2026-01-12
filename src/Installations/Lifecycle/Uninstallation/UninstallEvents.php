<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Lifecycle\Uninstallation;

final class UninstallEvents
{
    public const RUN_START = 'uninstall.run.start';
    public const RUN_END = 'uninstall.run.end';
    public const RUN_FAIL = 'uninstall.run.fail';

    public const DEACTIVATION_START = 'uninstall.deactivation.start';
    public const DEACTIVATION_OK = 'uninstall.deactivation.ok';
    public const DEACTIVATION_FAIL = 'uninstall.deactivation.fail';

    public const FILES_DELETE_START = 'uninstall.files.delete.start';
    public const FILES_DELETE_OK = 'uninstall.files.delete.ok';
    public const FILES_DELETE_FAIL = 'uninstall.files.delete.fail';

    public const DB_DELETE_START = 'uninstall.db.delete.start';
    public const DB_DELETE_OK = 'uninstall.db.delete.ok';
    public const DB_DELETE_FAIL = 'uninstall.db.delete.fail';
}