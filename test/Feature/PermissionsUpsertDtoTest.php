<?php

declare(strict_types=1);

namespace Tests\Feature;

use stdClass;
use Tests\PackageTestCase;
use Tests\Support\CreatesPlugin;
use Timeax\FortiPlugin\Facades\Permissions;

// concretes (to assert persisted rows)
use Timeax\FortiPlugin\Models\DbPermission;
use Timeax\FortiPlugin\Models\FilePermission;
use Timeax\FortiPlugin\Models\NotificationPermission;
use Timeax\FortiPlugin\Models\ModulePermission;
use Timeax\FortiPlugin\Models\NetworkPermission;
use Timeax\FortiPlugin\Models\CodecPermission;
use Timeax\FortiPlugin\Models\PluginPermission;

// upsert DTOs
use Timeax\FortiPlugin\Permissions\Ingestion\Dto\DbUpsertDto;
use Timeax\FortiPlugin\Permissions\Ingestion\Dto\FileUpsertDto;
use Timeax\FortiPlugin\Permissions\Ingestion\Dto\NotificationUpsertDto;
use Timeax\FortiPlugin\Permissions\Ingestion\Dto\ModuleUpsertDto;
use Timeax\FortiPlugin\Permissions\Ingestion\Dto\NetworkUpsertDto;
use Timeax\FortiPlugin\Permissions\Ingestion\Dto\CodecUpsertDto;

final class PermissionsUpsertDtoTest extends PackageTestCase
{
    use CreatesPlugin;

    public function test_upsert_db_idempotent_and_runtime(): void
    {
        $plugin = $this->createPlugin();

        $rule = [
            'type' => 'db',
            'actions' => ['select', 'insert'],
            'target' => ['table' => 'orders', 'columns' => ['id', 'total']],
            'audit' => ['redact_fields' => ['total']],
        ];
        $dto = DbUpsertDto::fromNormalized($rule);

        // first upsert → created = true
        $r1 = Permissions::upsert($plugin->getKey(), $dto);
        $this->assertTrue($r1->created);
        $this->assertSame('db', $r1->type);
        $this->assertNotEmpty($r1->natural_key);

        // concrete persisted (by natural_key)
        $db = DbPermission::query()->where('natural_key', $r1->natural_key)->first();
        $this->assertNotNull($db);

        // pivot created
        $pivot = PluginPermission::query()
            ->where('plugin_id', $plugin->getKey())
            ->where('permission_type', 'db')
            ->where('permission_id', $db->getKey())
            ->first();
        $this->assertNotNull($pivot);

        // idempotence → created = false second time, same concrete
        $r2 = Permissions::upsert($plugin->getKey(), $dto);
        $this->assertFalse($r2->created);
        $this->assertSame($r1->concrete_id, $r2->concrete_id);

        // runtime checks (cache is warmed by upsert)
        $ok = Permissions::canDb($plugin->getKey(), 'select', ['table' => 'orders', 'columns' => ['id']]);
        $this->assertTrue($ok->allowed);

        $no = Permissions::canDb($plugin->getKey(), 'update', ['table' => 'orders', 'columns' => ['total']]);
        $this->assertFalse($no->allowed);
    }

    public function test_upsert_file_and_runtime(): void
    {
        $plugin = $this->createPlugin();

        $rule = [
            'type' => 'file',
            'actions' => ['read', 'write'],
            'target' => ['base_dir' => '/var/data', 'paths' => ['reports/', 'logs/']],
        ];
        $dto = FileUpsertDto::fromNormalized($rule);

        $r = Permissions::upsert($plugin->getKey(), $dto);
        $this->assertTrue($r->created);
        $file = FilePermission::query()->where('natural_key', $r->natural_key)->first();
        $this->assertNotNull($file);

        $ok = Permissions::canFile($plugin->getKey(), 'read', ['baseDir' => '/var/data', 'path' => 'reports/2024.csv']);
        $this->assertTrue($ok->allowed);

        $no = Permissions::canFile($plugin->getKey(), 'delete', ['baseDir' => '/var/data', 'path' => 'reports/2024.csv']);
        $this->assertFalse($no->allowed);
    }

    public function test_upsert_notification_and_runtime(): void
    {
        $plugin = $this->createPlugin();

        $rule = [
            'type' => 'notification',
            'actions' => ['send'],
            'target' => [
                'channels' => ['email'],
                'templates' => ['welcome'],
                'recipients' => ['user@example.com'],
            ],
        ];
        $dto = NotificationUpsertDto::fromNormalized($rule);

        $r = Permissions::upsert($plugin->getKey(), $dto);
        $this->assertTrue($r->created);
        $np = NotificationPermission::query()->where('natural_key', $r->natural_key)->first();
        $this->assertNotNull($np);

        $ok = Permissions::canNotify($plugin->getKey(), 'send', [
            'channel' => 'email', 'template' => 'welcome', 'recipient' => 'user@example.com'
        ]);
        $this->assertTrue($ok->allowed);

        $no = Permissions::canNotify($plugin->getKey(), 'send', ['channel' => 'sms', 'template' => 'welcome']);
        $this->assertFalse($no->allowed);
    }

    public function test_upsert_module_and_runtime(): void
    {
        $plugin = $this->createPlugin();

        $rule = [
            'type' => 'module',
            'actions' => ['call'],
            'target' => ['plugin' => 'analytics', 'apis' => ['track', 'identify']],
        ];
        $dto = ModuleUpsertDto::fromNormalized($rule);

        $r = Permissions::upsert($plugin->getKey(), $dto);
        $this->assertTrue($r->created);
        $mp = ModulePermission::query()->where('natural_key', $r->natural_key)->first();
        $this->assertNotNull($mp);

        $ok = Permissions::canModule($plugin->getKey(), ['module' => 'analytics', 'api' => 'track']);
        $this->assertTrue($ok->allowed);

        $no = Permissions::canModule($plugin->getKey(), ['module' => 'analytics', 'api' => 'group']);
        $this->assertFalse($no->allowed);
    }

    public function test_upsert_network_and_runtime(): void
    {
        $plugin = $this->createPlugin();

        $rule = [
            'type' => 'network',
            'actions' => ['request'],
            'target' => [
                'hosts' => ['api.stripe.com', '*.timeax.dev'],
                'methods' => ['GET', 'POST'],
                'schemes' => ['https'],
                'ports' => [443],
                'paths' => ['/v1/', '/api/'],
            ],
        ];
        $dto = NetworkUpsertDto::fromNormalized($rule);

        $r = Permissions::upsert($plugin->getKey(), $dto);
        $this->assertTrue($r->created);
        $net = NetworkPermission::query()->where('natural_key', $r->natural_key)->first();
        $this->assertNotNull($net);

        $ok = Permissions::canNetwork($plugin->getKey(), ['method' => 'GET', 'url' => 'https://api.stripe.com/v1/charges']);
        $this->assertTrue($ok->allowed);

        $no = Permissions::canNetwork($plugin->getKey(), ['method' => 'GET', 'url' => 'http://api.stripe.com/v1/charges']);
        $this->assertFalse($no->allowed);
    }

    public function test_upsert_codec_and_runtime(): void
    {
        $plugin = $this->createPlugin();

        $rule = [
            'type' => 'codec',
            'actions' => ['invoke'],
            // post-normalization, codec rules may put allow-list fields at top-level
            'methods' => ['json_encode', 'json_decode'],
            'target' => 'codec',
        ];
        $dto = CodecUpsertDto::fromNormalized($rule);

        $r = Permissions::upsert($plugin->getKey(), $dto);
        $this->assertTrue($r->created);
        $codec = CodecPermission::query()->where('natural_key', $r->natural_key)->first();
        $this->assertNotNull($codec);

        $ok = Permissions::canCodec($plugin->getKey(), ['method' => 'json_encode']);
        $this->assertTrue($ok->allowed);

        // unserialize should be denied unless explicitly allowlisted via options
        $no = Permissions::canCodec($plugin->getKey(), ['method' => 'unserialize', 'options' => ['class' => stdClass::class]]);
        $this->assertFalse($no->allowed);
    }
}