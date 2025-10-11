<?php

declare(strict_types=1);

use Orchestra\Testbench\TestCase;
use Timeax\FortiPlugin\Facades\Permissions;
use Timeax\FortiPlugin\Permissions\Contracts\PermissionServiceInterface;
use Timeax\FortiPlugin\Permissions\Contracts\PermissionRequestInterface;
use Timeax\FortiPlugin\Permissions\Evaluation\Dto\PermissionListResult;
use Timeax\FortiPlugin\Permissions\Evaluation\Dto\PermissionListSummary;
use Timeax\FortiPlugin\Permissions\Evaluation\Dto\Result;
use Timeax\FortiPlugin\Permissions\Evaluation\Dto\PermissionListOptions;
use Timeax\FortiPlugin\Permissions\Ingestion\Dto\IngestSummary;
use Timeax\FortiPlugin\Permissions\Ingestion\Dto\RuleIngestResult;

final class PermissionsFacadeTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        // Ensure package provider is loaded for bindings via testbench.yaml too.
        return [Timeax\FortiPlugin\FortiPluginServiceProvider::class];
    }

    private function bindMock(callable $configure): void
    {
        $mock = Mockery::mock(PermissionServiceInterface::class);
        $configure($mock);
        $this->app->instance(PermissionServiceInterface::class, $mock);
    }

    public function test_ingestManifest_is_proxied(): void
    {
        $pluginId = 42;
        $manifest = ['required_permissions' => []];
        $expected = new IngestSummary(0, 0, [], []);

        $this->bindMock(function ($mock) use ($pluginId, $manifest, $expected) {
            $mock->shouldReceive('ingestManifest')->once()->with($pluginId, $manifest)->andReturn($expected);
        });

        $this->assertSame($expected, Permissions::ingestManifest($pluginId, $manifest));
    }

    public function test_cache_methods_are_proxied(): void
    {
        $pluginId = 7;
        $this->bindMock(function ($mock) use ($pluginId) {
            $mock->shouldReceive('warmCache')->once()->with($pluginId);
            $mock->shouldReceive('invalidateCache')->once()->with($pluginId);
        });

        Permissions::warmCache($pluginId);
        Permissions::invalidateCache($pluginId);
        $this->addToAssertionCount(1); // if no exceptions, proxying worked
    }

    public function test_typed_can_methods_are_proxied_and_return_result(): void
    {
        $pluginId = 5;
        $ok = Result::allow();

        $this->bindMock(function ($mock) use ($pluginId, $ok) {
            $mock->shouldReceive('canDb')
                ->once()->with($pluginId, 'select', ['table' => 'users'], [])->andReturn($ok);
            $mock->shouldReceive('canFile')
                ->once()->with($pluginId, 'read', ['baseDir' => '/tmp', 'path' => 'a'], ['guard' => 'x'])->andReturn($ok);
            $mock->shouldReceive('canNotify')
                ->once()->with($pluginId, 'send', ['channel' => 'email'], [])->andReturn($ok);
            $mock->shouldReceive('canModule')
                ->once()->with($pluginId, ['module' => 'analytics', 'api' => 'track'], [])->andReturn($ok);
            $mock->shouldReceive('canNetwork')
                ->once()->with($pluginId, ['method' => 'GET', 'url' => 'https://api.example.com'], [])->andReturn($ok);
            $mock->shouldReceive('canCodec')
                ->once()->with($pluginId, ['method' => 'json_encode', 'options' => []], [])->andReturn($ok);
            $mock->shouldReceive('canRouteWrite')
                ->once()->with($pluginId, ['routeId' => 'install.x', 'guard' => 'admin'], [])->andReturn($ok);
        });

        $this->assertTrue(Permissions::canDb($pluginId, 'select', ['table' => 'users'], [])->allowed);
        $this->assertTrue(Permissions::canFile($pluginId, 'read', ['baseDir' => '/tmp', 'path' => 'a'], ['guard' => 'x'])->allowed);
        $this->assertTrue(Permissions::canNotify($pluginId, 'send', ['channel' => 'email'], [])->allowed);
        $this->assertTrue(Permissions::canModule($pluginId, ['module' => 'analytics', 'api' => 'track'], [])->allowed);
        $this->assertTrue(Permissions::canNetwork($pluginId, ['method' => 'GET', 'url' => 'https://api.example.com'], [])->allowed);
        $this->assertTrue(Permissions::canCodec($pluginId, ['method' => 'json_encode', 'options' => []], [])->allowed);
        $this->assertTrue(Permissions::canRouteWrite($pluginId, ['routeId' => 'install.x', 'guard' => 'admin'], [])->allowed);
    }

    public function test_generic_can_method_is_proxied(): void
    {
        $pluginId = 9;
        $request = Mockery::mock(PermissionRequestInterface::class);
        $expected = Result::deny('nope');

        $this->bindMock(function ($mock) use ($pluginId, $request, $expected) {
            $mock->shouldReceive('can')->once()->with($pluginId, $request, [])->andReturn($expected);
        });

        $out = Permissions::can($pluginId, $request, []);
        $this->assertSame($expected, $out);
        $this->assertFalse($out->allowed);
    }

    public function test_upsert_and_validate_and_list_permissions_are_proxied(): void
    {
        $pluginId = 1;
        $dto = Mockery::mock(Timeax\FortiPlugin\Permissions\Contracts\UpsertDtoInterface::class);
        $ingestResult = new RuleIngestResult(type: 'db', natural_key: 'x', concrete_id: 1, concrete_Type: 'db', created: true, assigned: true, warning: null);
        $validated = ['ok' => true];
        $options = new PermissionListOptions();

        // Create a proper PermissionListResult object instead of array
        $summary = new PermissionListSummary(
            byType: [],
            total: 0,
            active: 0,
            inactive: 0,
            requiredTotal: 0,
            requiredSatisfied: 0,
            requiredPending: 0
        );
        $listResult = new PermissionListResult([], $summary);

        $this->bindMock(function ($mock) use ($pluginId, $dto, $ingestResult, $validated, $options, $listResult) {
            $mock->shouldReceive('upsert')->once()->with($pluginId, $dto, [])->andReturn($ingestResult);
            $mock->shouldReceive('validateManifest')->once()->with(['a' => 1])->andReturn($validated);
            $mock->shouldReceive('listPermissions')->once()->with($pluginId, $options)->andReturn($listResult);
        });

        $this->assertSame($ingestResult, Permissions::upsert($pluginId, $dto, []));
        $this->assertSame($validated, Permissions::validateManifest(['a' => 1]));

        $result = Permissions::listPermissions($pluginId, $options);
        $this->assertInstanceOf(PermissionListResult::class, $result);
        $this->assertSame(0, $result->summary->total);
    }
}
