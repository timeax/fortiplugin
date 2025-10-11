<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\PackageTestCase;
use Tests\Support\CreatesPlugin;
use Timeax\FortiPlugin\Enums\PluginStatus;
use Timeax\FortiPlugin\Facades\Permissions;
use Timeax\FortiPlugin\Models\PermissionTag;
use Timeax\FortiPlugin\Models\PermissionTagItem;
use Timeax\FortiPlugin\Models\PluginPermission;
use Timeax\FortiPlugin\Models\PluginPermissionTag;
use Timeax\FortiPlugin\Permissions\Evaluation\Dto\PermissionListOptions;

final class PermissionsListingAndTagsTest extends PackageTestCase
{
    use CreatesPlugin;

    /** Helper to attach a tag to a plugin. */
    private function attachTag(int $pluginId, string $name = 'suite-core'): PermissionTag
    {
        $tag = PermissionTag::query()->firstOrCreate(
            ['name' => $name],
            ['description' => 'Test suite tag', 'is_system' => false, 'status' => PluginStatus::active]
        );

        PluginPermissionTag::query()->updateOrCreate(
            ['plugin_id' => $pluginId, 'tag_id' => (int)$tag->getKey()],
            ['active' => true, 'limited' => false, 'limit_type' => null, 'limit_value' => null]
        );

        return $tag;
    }

    /** A “biggish” manifest with required & optional items across types. */
    private function bigManifest(): array
    {
        return [
            'required_permissions' => [
                // DB required (we’ll grant)
                ['type' => 'db', 'actions' => ['select', 'insert'], 'target' => ['table' => 'orders', 'columns' => ['id','total']]],
                // FILE required (we’ll grant)
                ['type' => 'file', 'actions' => ['read'], 'target' => ['base_dir' => '/srv', 'paths' => ['exports/','logs/']]],
                // NETWORK required (we *won’t* grant to simulate pending)
                ['type' => 'network', 'actions' => ['request'], 'target' => ['hosts' => ['api.missing.test'], 'methods' => ['GET']]],
            ],
            'optional_permissions' => [
                // NOTIFY optional
                ['type' => 'notification', 'actions' => ['send'], 'target' => ['channels' => ['email']]],
                // CODEC optional
                ['type' => 'codec', 'actions' => ['invoke'], 'target' => 'codec', 'methods' => ['json_encode']],
            ],
        ];
    }

    public function test_list_permissions_summary_math_and_filters(): void
    {
        $plugin = $this->createPlugin();

        // Ingest only the provided manifest (so the required NETWORK remains ungranted → pending)
        Permissions::ingestManifest($plugin->getKey(), $this->bigManifest());
        Permissions::warmCache($plugin->getKey());

        // Full listing
        $all = Permissions::listPermissions($plugin->getKey(), new PermissionListOptions());
        $this->assertNotNull($all->summary, 'Summary must exist');

        // Summary expectations:
        // - At least the two required we granted (DB + FILE) are satisfied.
        // - At least one required pending (NETWORK) remains.
        $this->assertGreaterThanOrEqual(2, $all->summary->requiredSatisfied, 'Expected >=2 required satisfied');
        $this->assertGreaterThanOrEqual(1, $all->summary->requiredPending, 'Expected >=1 required pending');

        // Check byType presence for DB/FILE (granted)
        $this->assertArrayHasKey('db', $all->summary->byType);
        $this->assertArrayHasKey('file', $all->summary->byType);
        $this->assertGreaterThanOrEqual(1, $all->summary->byType['db']['total'] ?? 0);
        $this->assertGreaterThanOrEqual(1, $all->summary->byType['file']['total'] ?? 0);

        // Filter: only required
        $requiredOnly = Permissions::listPermissions($plugin->getKey(), new PermissionListOptions(requiredOnly: true));
        $this->assertSame($requiredOnly->summary->total, $requiredOnly->summary->requiredTotal, 'Required-only should match requiredTotal');

        // Filter: by types
        $filesOnly = Permissions::listPermissions($plugin->getKey(), new PermissionListOptions(type: 'file'));
        $this->assertSame(1, $filesOnly->summary->byType['file']['total'] ?? 0, 'Expect exactly one file grant in this setup');

        $notifyOnly = Permissions::listPermissions($plugin->getKey(), new PermissionListOptions(type: 'notification'));
        $this->assertGreaterThanOrEqual(1, $notifyOnly->summary->byType['notification']['total'] ?? 0);
    }

    public function test_list_permissions_includes_tag_inherited_items_and_constraints(): void
    {
        $plugin = $this->createPlugin();

        // Seed a concrete MODULE permission via a helper plugin…
        $helper = $this->createPlugin(['name' => 'Seeder']);
        Permissions::ingestManifest($helper->getKey(), [
            'required_permissions' => [[
                'type' => 'module',
                'actions' => ['call'],
                'target' => ['plugin' => 'analytics', 'apis' => ['identify','track']],
                'audit' => ['redact_fields' => ['Authorization']], // ensure this flows through
            ]],
        ]);

        // Find helper's direct MODULE pivot to get the concrete id
        $helperModulePivot = PluginPermission::query()
            ->where('plugin_id', $helper->getKey())
            ->where('permission_type', 'module')
            ->firstOrFail();

        // Attach to a tag (with tag-level constraints/audit)
        $tag = $this->attachTag($plugin->getKey(), 'analytics-bundle');
        PermissionTagItem::query()->updateOrCreate(
            [
                'tag_id' => (int)$tag->getKey(),
                'permission_type' => 'module',
                'permission_id' => (int)$helperModulePivot->permission_id,
            ],
            [
                'constraints' => ['guard' => 'api'],
                'audit'       => ['redact_fields' => ['Authorization']],
            ]
        );

        // Also ingest the big manifest for the main plugin (to mix direct + via-tag)
        Permissions::ingestManifest($plugin->getKey(), $this->bigManifest());
        Permissions::warmCache($plugin->getKey());

        // Listing should include a module grant via tag
        $listing = Permissions::listPermissions($plugin->getKey(), new PermissionListOptions());
        $this->assertArrayHasKey('module', $listing->summary->byType, 'Module type should appear via tag inheritance');
        $this->assertGreaterThanOrEqual(1, $listing->summary->byType['module']['total'] ?? 0);

        // Filter down to modules and check we still see at least one
        $modulesOnly = Permissions::listPermissions($plugin->getKey(), new PermissionListOptions(type: 'module'));
        $this->assertGreaterThanOrEqual(1, $modulesOnly->summary->byType['module']['total'] ?? 0);

        // Quick smoke-check: a module call allowed through tag constraints (guard=api)
        $ok = Permissions::canModule($plugin->getKey(), ['module' => 'analytics', 'api' => 'identify'], ['guard' => 'api']);
        $this->assertTrue($ok->allowed, 'Module call should be allowed via tag with guard=api');
    }
}