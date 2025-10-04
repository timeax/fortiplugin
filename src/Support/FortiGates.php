<?php /** @noinspection ALL */

namespace Timeax\FortiPlugin\Support;

/**
 * Centralised list of Gate keys for FortiPlugin.
 * Use kebab-case keys consistently across policies, middleware, and UI.
 */
final class FortiGates
{
    // ── Core / Admin UI ───────────────────────────────────────────────────────
    public const VIEW_DASHBOARD = 'forti-view-dashboard';
    public const MANAGE_SETTINGS = 'forti-manage-settings';
    public const CLEAR_CACHE = 'forti-clear-cache';

    // ── Policy Management ─────────────────────────────────────────────────────
    public const POLICY_READ = 'forti-policy-read';
    public const POLICY_EDIT = 'forti-policy-edit';
    public const POLICY_PUBLISH = 'forti-policy-publish';
    // ── Keys / Certificates (packager & host trust) ──────────────────────────
    public const KEYS_VIEW = 'forti-keys-view';
    public const KEYS_ISSUE = 'forti-keys-issue';
    public const KEYS_REVOKE = 'forti-keys-revoke';
    public const KEYS_ROTATE = 'forti-keys-rotate';

    // ── Packager API (developer side ↔ host) ─────────────────────────────────
    public const PACKAGER_FETCH_POLICY = 'forti-packager-fetch-policy';
    public const PACKAGER_SUBMIT_REPORT = 'forti-packager-submit-report';
    public const PACKAGER_REGISTER_FINGERPRINT = 'forti-packager-register-fingerprint';

    // ── Plugin Lifecycle (host side) ─────────────────────────────────────────
    public const PLUGIN_UPLOAD = 'forti-plugin-upload';
    public const PLUGIN_SCAN = 'forti-plugin-scan';
    public const PLUGIN_VALIDATE = 'forti-plugin-validate';
    public const PLUGIN_INSTALL = 'forti-plugin-install';
    public const PLUGIN_FORCE_INSTALL = 'forti-plugin-force-install'; // explicit override
    public const PLUGIN_ENABLE = 'forti-plugin-enable';
    public const PLUGIN_DISABLE = 'forti-plugin-disable';
    public const PLUGIN_REMOVE = 'forti-plugin-remove';
    public const PLUGIN_UPDATE = 'forti-plugin-update';
    public const PLUGIN_LIST = 'forti-plugin-list';

    // Reports & provenance
    public const PLUGIN_VIEW_REPORT = 'forti-plugin-view-report';
    public const PLUGIN_DOWNLOAD_REPORT = 'forti-plugin-download-report';

    // ── Permission Manifests (per-plugin capability grants) ──────────────────
    public const PERMS_MANAGE = 'forti-perms-manage';
    public const PERMS_DB_MANAGE = 'forti-perms-db-manage';
    public const PERMS_FILE_MANAGE = 'forti-perms-file-manage';
    public const PERMS_HTTP_MANAGE = 'forti-perms-http-manage';
    public const PERMS_ROUTE_MANAGE = 'forti-perms-route-manage';
    public const PERMS_NOTIFICATION_MANAGE = 'forti-perms-notification-manage';

    // ── Secure Route Registrar (undeclared routes queue) ─────────────────────
    public const ROUTE_VIEW_QUEUE = 'forti-route-view-queue';
    public const ROUTE_APPROVE = 'forti-route-approve';
    public const ROUTE_DENY = 'forti-route-deny';
    public const ROUTE_ASSIGN_PERMISSIONS = 'forti-route-assign-permissions';

    // ── Audit & Validation Queues ────────────────────────────────────────────
    public const AUDIT_VIEW = 'forti-audit-view';
    public const AUDIT_EXPORT = 'forti-audit-export';
    public const AUDIT_ARCHIVE = 'forti-audit-archive';
    public const AUDIT_PRUNE = 'forti-audit-prune';

    public const VALIDATION_VIEW_QUEUE = 'forti-validation-view-queue';
    public const VALIDATION_REVIEW = 'forti-validation-review';
    public const VALIDATION_APPROVE = 'forti-validation-approve';
    public const VALIDATION_REJECT = 'forti-validation-reject';

    // ── Diagnostics / Tools ──────────────────────────────────────────────────
    public const DIAGNOSTICS_VIEW = 'forti-diagnostics-view';
    public const DIAGNOSTICS_RUN = 'forti-diagnostics-run';

    // app/Support/FortiGates.php (excerpt)

    // ── Authors ───────────────────────────────────────────────────────────────
    public const AUTHOR_VIEW = 'forti-author-view';
    public const AUTHOR_VERIFY = 'forti-author-verify';
    public const AUTHOR_UPDATE = 'forti-author-update';
    public const AUTHOR_ACTIVATE = 'forti-author-activate';
    public const AUTHOR_DEACTIVATE = 'forti-author-deactivate';
    public const AUTHOR_BLOCK = 'forti-author-block';

    public const AUTHOR_VIEW_PLUGINS = 'forti-author-view-plugins';
    public const AUTHOR_VIEW_ISSUES = 'forti-author-view-issues';
// Timeax\FortiPlugin\Support\FortiGates (additions)

// ── Authors/Auth ─────────────────────────────────────────────────────────────
    public const AUTHOR_LOGIN = 'forti-author-login';
    public const AUTHOR_LOGOUT = 'forti-author-logout';
    public const TOKEN_ISSUE_AUTHOR = 'forti-token-issue-author';
    public const TOKEN_REVOKE_AUTHOR = 'forti-token-revoke-author';

// ── Placeholders & tokens (init handshake) ───────────────────────────────────
    public const PLACEHOLDER_CREATE = 'forti-placeholder-create';
    public const TOKEN_ISSUE_PLACEHOLDER = 'forti-token-issue-placeholder';
    // ── Issues ────────────────────────────────────────────────────────────────
    public const ISSUE_CREATE = 'forti-issue-create';
    public const ISSUE_VIEW = 'forti-issue-view';
    public const ISSUE_COMMENT = 'forti-issue-comment';
    public const ISSUE_UPDATE_STATUS = 'forti-issue-update-status';
}