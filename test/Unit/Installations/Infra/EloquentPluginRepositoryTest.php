<?php

declare(strict_types=1);

namespace Tests\Unit\Installations\Infra;

use Timeax\FortiPlugin\Installations\DTO\InstallMeta;
use Timeax\FortiPlugin\Installations\Infra\EloquentPluginRepository;
use Timeax\FortiPlugin\Models\Plugin;
use Tests\PackageTestCase;
use Tests\Support\CreatesPlugin;

final class EloquentPluginRepositoryTest extends PackageTestCase
{
    use CreatesPlugin;

    private EloquentPluginRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new EloquentPluginRepository();
    }

    public function test_upsertPlugin_persists_alias_and_name(): void
    {
        $ph = $this->createPlaceholder(['slug' => 'my-awesome-plugin']);

        $meta = new InstallMeta(
            psr4_root: 'Plugins',
            placeholder_name: 'My Awesome Plugin',
            placeholder_slug: $ph->slug,
            plugin_placeholder_id: $ph->id,
            zip_id: 456,
            actor: 'test-user',
            paths: [],
            started_at: now()->toIso8601String(),
            updated_at: now()->toIso8601String(),
            fingerprint: 'test-fingerprint',
            validator_config_hash: 'test-hash'
        );

        $pluginId = $this->repository->upsertPlugin($meta);

        $this->assertNotNull($pluginId);

        $plugin = Plugin::find($pluginId);
        $this->assertSame('my-awesome-plugin', $plugin->alias);
        $this->assertSame('My Awesome Plugin', $plugin->name);
        $this->assertSame($ph->id, $plugin->plugin_placeholder_id);
        
        $installMeta = $plugin->meta['install_meta'] ?? [];
        $this->assertSame('my-awesome-plugin', $installMeta['placeholder_slug']);
    }

    public function test_upsertPlugin_updates_existing_plugin_by_alias(): void
    {
        $ph = $this->createPlaceholder(['slug' => 'old-alias', 'id' => 123]);

        // First create a plugin
        $plugin = new Plugin();
        $plugin->alias = 'old-alias';
        $plugin->name = 'Old Name';
        $plugin->plugin_placeholder_id = $ph->id;
        $plugin->save();

        $meta = new InstallMeta(
            psr4_root: 'Plugins',
            placeholder_name: 'New Name',
            placeholder_slug: 'old-alias', // Match by alias
            plugin_placeholder_id: $ph->id,
            zip_id: 456,
            actor: 'test-user',
            paths: [],
            started_at: now()->toIso8601String(),
            updated_at: now()->toIso8601String(),
            fingerprint: 'test-fingerprint',
            validator_config_hash: 'test-hash'
        );

        $pluginId = $this->repository->upsertPlugin($meta);

        $this->assertSame($plugin->id, $pluginId);
        
        $updatedPlugin = Plugin::find($pluginId);
        $this->assertSame('New Name', $updatedPlugin->name);
        $this->assertSame('old-alias', $updatedPlugin->alias);
    }
}
