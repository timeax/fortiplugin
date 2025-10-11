<?php

declare(strict_types=1);

namespace Tests\Feature;

use stdClass;
use Tests\PackageTestCase;
use Tests\Support\CreatesPlugin;
use Timeax\FortiPlugin\Facades\Permissions;
use Timeax\FortiPlugin\Models\PluginRoutePermission;
use Timeax\FortiPlugin\Enums\RoutePermissionStatus;

final class PermissionsServiceIntegrationTest extends PackageTestCase
{
    use CreatesPlugin;

    /** Build a big, normalized-ish manifest for tests. */
    private function makeManifest(): array
    {
        return [
            'required_permissions' => [
                // DB: select on 'user' (alias resolved by validator), restrict columns
                [
                    'type' => 'db',
                    'actions' => ['select'],
                    'target' => ['model' => 'user', 'columns' => ['id', 'name']],
                    'audit' => ['log' => 'always', 'redact_fields' => ['email']],
                ],

                // FILE: read+write under /var/data
                [
                    'type' => 'file',
                    'actions' => ['read', 'write', 'append', 'list'],
                    'target' => ['base_dir' => '/var/data', 'paths' => ['reports/', 'logs/']],
                ],

                // NETWORK: allow https GET/POST to api.stripe.com and *.timeax.dev under /v1 or /api
                [
                    'type' => 'network',
                    'actions' => ['request'],
                    'target' => [
                        'hosts' => ['api.stripe.com', '*.timeax.dev'],
                        'methods' => ['GET', 'POST'],
                        'schemes' => ['https'],
                        'ports' => [443],
                        'paths' => ['/v1/', '/api/'],
                        'headers_allowed' => ['authorization'],
                    ],
                ],

                // NOTIFY: send email (specific template)
                [
                    'type' => 'notification',
                    'actions' => ['send'],
                    'target' => [
                        'channels' => ['email'],
                        'templates' => ['welcome'],
                        'recipients' => ['user@example.com'],
                    ],
                ],

                // MODULE: call analytics.track/identify
                [
                    'type' => 'module',
                    'actions' => ['call'],
                    'target' => ['plugin' => 'analytics', 'apis' => ['track', 'identify']],
                ],

                // CODEC: invoke json_* methods
                [
                    'type' => 'codec',
                    'actions' => ['invoke'],
                    'target' => 'codec',
                    'methods' => ['json_encode', 'json_decode'],
                ],
            ],

            'optional_permissions' => [
                // Another network rule we’ll use to test a deny (different host)
                [
                    'type' => 'network',
                    'actions' => ['request'],
                    'target' => [
                        'hosts' => ['*.example.com'],
                        'methods' => ['GET'],
                        'schemes' => ['https'],
                    ],
                ],
            ],
        ];
    }

    public function test_ingest_and_all_checks(): void
    {
        $plugin = $this->createPlugin();

        // Ingest and warm cache
        $summary = Permissions::ingestManifest($plugin->getKey(), $this->makeManifest());
        $this->assertGreaterThan(0, $summary->created + $summary->linked);
        Permissions::warmCache($plugin->getKey());

        // DB allow + deny
        $ok = Permissions::canDb($plugin->getKey(), 'select', ['model' => 'user', 'columns' => ['id', 'name']]);
        $this->assertTrue($ok->allowed, 'DB select should be allowed');

        $no = Permissions::canDb($plugin->getKey(), 'update', ['model' => 'user', 'columns' => ['email']]);
        $this->assertFalse($no->allowed, 'DB update (not granted) should be denied');

        // FILE allow + deny
        $ok = Permissions::canFile($plugin->getKey(), 'read', ['baseDir' => '/var/data', 'path' => 'reports/2024.csv']);
        $this->assertTrue($ok->allowed);

        $no = Permissions::canFile($plugin->getKey(), 'delete', ['baseDir' => '/var/data', 'path' => 'reports/2024.csv']);
        $this->assertFalse($no->allowed, 'FILE delete not granted');

        // NETWORK allow + denials
        $ok = Permissions::canNetwork($plugin->getKey(), [
            'method' => 'GET',
            'url' => 'https://api.stripe.com/v1/charges',
        ]);
        $this->assertTrue($ok->allowed, 'Network stripe allow');

        $no = Permissions::canNetwork($plugin->getKey(), [
            'method' => 'GET',
            'url' => 'http://api.stripe.com/v1/charges', // http not allowed
        ]);
        $this->assertFalse($no->allowed, 'Network http should be denied');

        $no = Permissions::canNetwork($plugin->getKey(), [
            'method' => 'GET',
            'url' => 'https://api.evil.com/',
        ]);
        $this->assertFalse($no->allowed, 'Network other host denied');

        // NOTIFY
        $ok = Permissions::canNotify($plugin->getKey(), 'send', ['channel' => 'email', 'template' => 'welcome', 'recipient' => 'user@example.com']);
        $this->assertTrue($ok->allowed);

        $no = Permissions::canNotify($plugin->getKey(), 'send', ['channel' => 'sms', 'template' => 'welcome', 'recipient' => 'user@example.com']);
        $this->assertFalse($no->allowed, 'channel not granted');

        // MODULE
        $ok = Permissions::canModule($plugin->getKey(), ['module' => 'analytics', 'api' => 'track']);
        $this->assertTrue($ok->allowed);

        $no = Permissions::canModule($plugin->getKey(), ['module' => 'billing', 'api' => 'invoice']);
        $this->assertFalse($no->allowed);

        // CODEC
        $ok = Permissions::canCodec($plugin->getKey(), ['method' => 'json_encode']);
        $this->assertTrue($ok->allowed);

        // unserialize denied by default (no allowlist options)
        $no = Permissions::canCodec($plugin->getKey(), ['method' => 'unserialize', 'options' => ['class' => stdClass::class]]);
        $this->assertFalse($no->allowed, 'unserialize not allowlisted');
    }

    public function test_route_write_approval(): void
    {
        $plugin = $this->createPlugin();

        // Approved route
        $approved = new PluginRoutePermission([
            'plugin_id' => $plugin->getKey(),
            'route_id' => 'install.dashboard',
            'status' => RoutePermissionStatus::approved,
            'guard' => 'admin',
            'meta' => null,
            'approved_at' => now(),
        ]);
        $approved->save();

        $ok = Permissions::canRouteWrite($plugin->getKey(), ['routeId' => 'install.dashboard', 'guard' => 'admin']);
        $this->assertTrue($ok->allowed);

        // Pending route
        $pending = new PluginRoutePermission([
            'plugin_id' => $plugin->getKey(),
            'route_id' => 'install.settings',
            'status' => RoutePermissionStatus::pending,
            'guard' => 'admin',
        ]);
        $pending->save();

        $no = Permissions::canRouteWrite($plugin->getKey(), ['routeId' => 'install.settings', 'guard' => 'admin']);
        $this->assertFalse($no->allowed);
    }
}