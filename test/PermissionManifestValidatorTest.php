<?php

use Orchestra\Testbench\TestCase;
use Timeax\FortiPlugin\Core\Security\PermissionManifestValidator;

class PermissionManifestValidatorTest extends TestCase
{
    private function makeValidator(
        ?array $channels = null,
        ?array $models = null,
        ?array $modules = null
    ): PermissionManifestValidator {
        return new PermissionManifestValidator($channels, $models, $modules);
    }

    private function expectInvalid(callable $fn): string
    {
        try {
            $fn();
        } catch (InvalidArgumentException $e) {
            // return message for further assertions
            return $e->getMessage();
        }
        $this->fail('Expected InvalidArgumentException was not thrown');
    }

    // ---------------- Top-level ----------------

    public function testRejectsUnknownTopLevelKeys()
    {
        $v = $this->makeValidator();
        $msg = $this->expectInvalid(function () use ($v) {
            $v->validate([
                'required_permissions' => [],
                'oops' => true,
            ]);
        });
        $this->assertStringContainsString('$: unknown field(s): oops', $msg);
    }

    public function testRequiresRequiredPermissionsArray()
    {
        $v = $this->makeValidator();
        $msg = $this->expectInvalid(function () use ($v) {
            $v->validate(['required_permissions' => 'nope']);
        });
        $this->assertStringContainsString('$.required_permissions: required_permissions must be an array', $msg);
    }

    public function testAcceptsJsonStringManifest()
    {
        $v = $this->makeValidator();
        $json = json_encode([
            'required_permissions' => [
                ['type' => 'file', 'actions' => ['read'], 'target' => ['base_dir' => '/tmp', 'paths' => ['a']]],
            ],
        ], JSON_THROW_ON_ERROR);
        $out = $v->validate($json);
        $this->assertIsArray($out);
        $this->assertCount(1, $out['required_permissions']);
    }

    // ---------------- DB rules ----------------

    public function testDbModelAliasAndColumnsPolicy()
    {
        $channels = [];
        $models = [
            'user' => [
                'map' => 'App\\Models\\User',
                'columns' => [
                    'all' => ['id', 'name', 'email', 'age'],
                    'writable' => ['name', 'email'],
                ],
            ],
        ];
        $v = $this->makeValidator($channels, $models);

        // Read-only columns can be subset of all
        $ok = [
            'required_permissions' => [[
                'type' => 'db',
                'actions' => ['select'],
                'target' => [
                    'model' => 'user',
                    'columns' => ['id', 'name'],
                ],
            ]],
        ];
        $out = $v->validate($ok);
        $this->assertSame('user', $out['required_permissions'][0]['target']['model_alias']);
        $this->assertSame('App\\Models\\User', $out['required_permissions'][0]['target']['model']);

        // Write requires subset of writable
        $bad = [
            'required_permissions' => [[
                'type' => 'db',
                'actions' => ['insert'],
                'target' => [
                    'model' => 'user',
                    'columns' => ['age'],
                ],
            ]],
        ];
        $msg = $this->expectInvalid(function () use ($v, $bad) { $v->validate($bad); });
        $this->assertStringContainsString('columns not writable by host policy: age', $msg);
    }

    public function testDbUnknownModelWhenCatalogPresent()
    {
        $models = [ 'post' => ['map' => 'App\\Models\\Post'] ];
        $v = $this->makeValidator([], $models);
        $data = [
            'required_permissions' => [[
                'type' => 'db',
                'actions' => ['select'],
                'target' => ['model' => 'user'],
            ]],
        ];
        $msg = $this->expectInvalid(function () use ($v, $data) { $v->validate($data); });
        $this->assertStringContainsString("$.required_permissions[0].target.model: unknown model alias/FQCN 'user'", $msg);
    }

    public function testDbExactlyOneOfModelOrTable()
    {
        $v = $this->makeValidator();
        $data = [
            'required_permissions' => [[
                'type' => 'db',
                'actions' => ['select'],
                'target' => ['model' => 'User', 'table' => 'users'],
            ]],
        ];
        $msg = $this->expectInvalid(function () use ($v, $data) { $v->validate($data); });
        $this->assertStringContainsString("$.required_permissions[0].target: exactly one of 'model' or 'table' is required", $msg);
    }

    // ---------------- FILE rules ----------------

    public function testFilePathsValidation()
    {
        $v = $this->makeValidator();
        $ok = [
            'required_permissions' => [[
                'type' => 'file', 'actions' => ['read','list'],
                'target' => ['base_dir' => '/var', 'paths' => ['logs','app/file.txt']]
            ]],
        ];
        $this->assertIsArray($v->validate($ok));

        $bad = [
            'required_permissions' => [[
                'type' => 'file', 'actions' => ['read'],
                'target' => ['base_dir' => '/var', 'paths' => ['../etc/passwd']]
            ]],
        ];
        $msg = $this->expectInvalid(function () use ($v, $bad) { $v->validate($bad); });
        $this->assertStringContainsString("$.required_permissions[0].target.paths[0]: path must not contain '..'", $msg);
    }

    // ---------------- NETWORK rules ----------------

    public function testNetworkValidation()
    {
        $v = $this->makeValidator();
        $ok = [
            'required_permissions' => [[
                'type' => 'network', 'actions' => ['request'],
                'target' => [
                    'hosts' => ['api.example.com','*.sub.example.org'],
                    'methods' => ['get','POST'],
                    'schemes' => ['https'],
                    'ports' => [443],
                    'ips_allowed' => ['127.0.0.1'],
                ]
            ]],
        ];
        $out = $v->validate($ok);
        $this->assertSame(['GET','POST'], $out['required_permissions'][0]['target']['methods']);
        $this->assertTrue($out['required_permissions'][0]['target']['auth_via_host_secret']);

        $bad = [
            'required_permissions' => [[
                'type' => 'network', 'actions' => ['request'],
                'target' => [ 'hosts' => ['bad host'], 'methods' => ['TRACE'] ]
            ]],
        ];
        $msg = $this->expectInvalid(function () use ($v, $bad) { $v->validate($bad); });
        $this->assertStringContainsString('invalid host pattern', $msg);
        $this->assertStringContainsString("method 'TRACE' not allowed", $msg);
    }

    // ---------------- NOTIFY rules ----------------

    public function testNotifyChannelsAllowlist()
    {
        $v = $this->makeValidator(['email','sms']);
        // ok
        $ok = [
            'required_permissions' => [[
                'type' => 'notify', 'actions' => ['send'],
                'target' => [ 'channels' => ['email','sms'] ]
            ]],
        ];
        $this->assertIsArray($v->validate($ok));

        // bad
        $bad = [
            'required_permissions' => [[
                'type' => 'notify', 'actions' => ['send'],
                'target' => [ 'channels' => ['push'] ]
            ]],
        ];
        $msg = $this->expectInvalid(function () use ($v, $bad) { $v->validate($bad); });
        $this->assertStringContainsString("channel 'push' is not allowed by host", $msg);
    }

    // ---------------- MODULE rules ----------------

    public function testModuleAliasAndFqcnMapping()
    {
        $modules = [
            'analytics' => ['map' => 'Vendor\\Pkg\\Analytics', 'docs' => 'https://docs/analytics'],
        ];
        $v = $this->makeValidator(null, null, $modules);
        $ok = [
            'required_permissions' => [[
                'type' => 'module', 'actions' => ['call'],
                'target' => [ 'plugin' => 'analytics', 'apis' => ['track','identify'] ]
            ]],
        ];
        $out = $v->validate($ok);
        $t = $out['required_permissions'][0]['target'];
        $this->assertSame('analytics', $t['plugin_alias']);
        $this->assertSame('Vendor\\Pkg\\Analytics', $t['plugin_fqcn']);
        $this->assertSame('https://docs/analytics', $t['plugin_docs']);

        // FQCN declaration resolves alias
        $ok2 = [
            'required_permissions' => [[
                'type' => 'module', 'actions' => ['call'],
                'target' => [ 'plugin' => 'Vendor\\Pkg\\Analytics', 'apis' => ['track'] ]
            ]],
        ];
        $out2 = $v->validate($ok2);
        $this->assertSame('analytics', $out2['required_permissions'][0]['target']['plugin_alias']);
    }

    public function testModuleUnknownRejectedWhenCatalogPresent()
    {
        $v = $this->makeValidator(null, null, ['known' => ['map' => 'X']]);
        $data = [
            'required_permissions' => [[
                'type' => 'module', 'actions' => ['call'],
                'target' => [ 'plugin' => 'mystery', 'apis' => ['x'] ]
            ]],
        ];
        $msg = $this->expectInvalid(function () use ($v, $data) { $v->validate($data); });
        $this->assertStringContainsString("$.required_permissions[0].target.plugin: unknown module 'mystery'", $msg);
    }

    public function testModuleFreeFormWhenNoCatalog()
    {
        $v = $this->makeValidator();
        $data = [
            'required_permissions' => [[
                'type' => 'module', 'actions' => ['call'],
                'target' => [ 'plugin' => 'Vendor\\Tool', 'apis' => ['do'] ]
            ]],
        ];
        $out = $v->validate($data);
        $this->assertSame('Vendor\\Tool', $out['required_permissions'][0]['target']['plugin_fqcn']);
        $this->assertNull($out['required_permissions'][0]['target']['plugin_alias']);
    }

    // ---------------- CODEC rules ----------------

    public function testCodecMethodsRequireGuardWhenIncludingUnserialize()
    {
        $v = $this->makeValidator();
        $bad = [
            'required_permissions' => [[
                'type' => 'codec', 'actions' => ['invoke'],
                'target' => 'codec',
                'methods' => ['json_encode','unserialize']
            ]],
        ];
        $msg = $this->expectInvalid(function () use ($v, $bad) { $v->validate($bad); });
        $this->assertStringContainsString('options.allow_unserialize_classes is required', $msg);

        $ok = [
            'required_permissions' => [[
                'type' => 'codec', 'actions' => ['invoke'],
                'target' => 'codec',
                'methods' => ['json_encode','unserialize'],
                'options' => ['allow_unserialize_classes' => ['DateTime','StdClass']]
            ]],
        ];
        $out = $v->validate($ok);
        $rule = $out['required_permissions'][0];
        $this->assertTrue($rule['requires_unserialize_guard']);
        $this->assertContains('json_encode', $rule['resolved_methods']);
        $this->assertContains('unserialize', $rule['resolved_methods']);
    }

    public function testCodecGroupsResolutionAndWildcard()
    {
        $v = $this->makeValidator();
        // Using known group from Obfuscator::availableGroups() e.g., 'serialize' contains serialize/unserialize
        $bad = [
            'required_permissions' => [[
                'type' => 'codec', 'actions' => ['invoke'],
                'target' => 'codec',
                'groups' => ['serialize']
            ]],
        ];
        $msg = $this->expectInvalid(function () use ($v, $bad) { $v->validate($bad); });
        $this->assertStringContainsString('options.allow_unserialize_classes is required', $msg);

        $ok = [
            'required_permissions' => [[
                'type' => 'codec', 'actions' => ['invoke'],
                'target' => 'codec',
                'groups' => ['encoding','hash'],
            ]],
        ];
        $out = $v->validate($ok);
        $rule = $out['required_permissions'][0];
        $this->assertIsArray($rule['resolved_methods']);
        $this->assertFalse($rule['requires_unserialize_guard']);

        $ok2 = [
            'required_permissions' => [[
                'type' => 'codec', 'actions' => ['invoke'],
                'target' => 'codec',
                'methods' => '*',
                'options' => ['allow_unserialize_classes' => []]
            ]],
        ];
        $out2 = $v->validate($ok2);
        $this->assertSame('*', $out2['required_permissions'][0]['resolved_methods']);
        $this->assertTrue($out2['required_permissions'][0]['requires_unserialize_guard']);
    }

    // ---------------- Conditions & Audit ----------------

    public function testConditionsAndAuditNormalization()
    {
        $v = $this->makeValidator();
        $data = [
            'required_permissions' => [[
                'type' => 'file', 'actions' => ['read'],
                'target' => ['base_dir' => '/tmp', 'paths' => ['x']],
                'conditions' => [
                    'setting_link' => 'abc',
                    'guard' => 'feature-x',
                    'env' => [ 'allow' => ['prod'], 'deny' => ['ci'] ],
                ],
                'audit' => [ 'log' => 'on_deny', 'redact_fields' => ['secret'], 'tags' => ['p0'] ]
            ]],
        ];
        $out = $v->validate($data);
        $rule = $out['required_permissions'][0];
        $this->assertSame(['allow' => ['prod'], 'deny' => ['ci']], $rule['conditions']['env']);
        $this->assertSame('on_deny', $rule['audit']['log']);
    }
}
