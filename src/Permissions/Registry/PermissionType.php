<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Registry;

/**
 * Canonical permission types used across the system.
 * Keep string values stable — they are persisted & exchanged.
 */
enum PermissionType: string
{
    case DB           = 'db';
    case FILE         = 'file';
    case NOTIFICATION = 'notification';
    case MODULE       = 'module';
    case NETWORK      = 'network';
    case CODEC        = 'codec';
    case ROUTE        = 'route'; // checks only (no ingestor)
}