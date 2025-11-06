<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Support;

/**
 * Canonical error codes for $payload['error']['code'] and for summary/decision metadata.
 * Keep these stable—your UI and analytics will rely on them.
 */
final class ErrorCodes
{
    // — Composer / PSR-4
    public const COMPOSER_PSR4_MISSING_OR_MISMATCH = 'composer.psr4_mismatch';
    public const COMPOSER_SCAN_ERROR = 'composer.scan_error';

    // — Validation phases
    public const CONFIG_SCHEMA_ERROR = 'config.schema_error';
    public const HOST_CONFIG_INVALID = 'host.config_invalid';
    public const MANIFEST_INVALID = 'manifest.invalid';
    public const VALIDATION_ERRORS_FOUND = 'validation.errors_found';

    // — Routes
    public const ROUTE_SCHEMA_ERROR = 'route.schema_error';
    public const ROUTE_ID_DUPLICATE = 'route.id_duplicate';
    public const ROUTE_CONTROLLER_NAMESPACE_INVALID = 'route.controller_namespace_invalid';

    // — Security scanners
    public const CONTENT_VALIDATION_ERROR = 'scan.content_violation';
    public const TOKEN_USAGE_VIOLATION = 'scan.token_violation';
    public const AST_VIOLATION = 'scan.ast_violation';
    public const SCANNER_EXCEPTION = 'scan.scanner_exception';
    public const FILE_SCAN_ERRORS_FOUND = 'scan.errors_found';

    // — Zip / status gate
    public const ZIP_VALIDATION_PENDING = 'zip.validation_pending';
    public const ZIP_VALIDATION_FAILED = 'zip.validation_failed';
    public const ZIP_VALIDATION_UNKNOWN = 'zip.validation_unknown';
    public const ZIP_STATUS_FAILED_OR_UNKNOWN = 'zip.failed_or_unknown';

    // — Packages / Composer plan
    public const PACKAGES_FOREIGN_NEED_SCAN = 'packages.foreign_need_scan';
    public const PACKAGES_CORE_CONFLICT = 'packages.core_conflict'; // e.g. php, laravel/framework

    // — Tokens / permissions
    public const INSTALLER_TOKEN_REQUIRED = 'token.required';
    public const TOKEN_INVALID = 'token.invalid';
    public const TOKEN_EXPIRED = 'token.expired';
    public const TOKEN_PURPOSE_MISMATCH = 'token.purpose_mismatch';

    // — System / IO / DB
    public const FILESYSTEM_WRITE_FAILED = 'fs.write_failed';
    public const DB_PERSIST_FAILED = 'db.persist_failed';

    // — Installer outcomes
    public const INSTALLATION_DECISION_REQUIRED = 'install.decision_required';
    public const INSTALLATION_ABORTED = 'install.aborted';
    public const INSTALLATION_SUCCESS = 'install.success';
    public const FILESYSTEM_READ_FAILED = 'fs.read_failed';
    public const UI_PROP_ENUM_VIOLATION = 'ui.prop_enum_violation';
    public const UI_PROP_TYPE_MISMATCH = 'ui.prop_type_mismatch';
    public const UI_UNKNOWN_PROP = 'ui.unknown_prop';
    public const UI_DUPLICATE_ITEM = 'ui.duplicate_item';
    public const UI_HREF_SUSPECT = 'ui.href_suspect';
    public const UI_ROUTE_ID_MISSING = 'ui.route_id_missing';
    public const UI_SECTION_NOT_EXTENDABLE = 'ui.section_not_extendable';
    public const UI_TARGET_NOT_EXTENDABLE = 'ui.target_not_extendable';
    public const UI_TARGET_NOT_FOUND = 'ui.target_not_found';
    public const UI_SECTION_NOT_FOUND = 'ui.section_not_found';
    public const UI_ITEM_INVALID = 'ui.item_invalid';
    public const CONFIG_READ_FAILED = 'config.read_failed';
}