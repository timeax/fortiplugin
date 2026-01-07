<?php

declare(strict_types=1);

namespace Tests\Core\Install;

use PHPUnit\Framework\TestCase;
use Timeax\FortiPlugin\Core\Exceptions\RouteCompileException;
use Timeax\FortiPlugin\Core\Install\JsonRouteCompiler;

final class JsonRouteCompilerTest extends TestCase
{
    private function compiler(): JsonRouteCompiler
    {
        return new JsonRouteCompiler();
    }

    /* -----------------------------------------------------------------
     |  LEGACY FLOW TESTS (compileData) - Ensuring no regressions
     | -----------------------------------------------------------------
     */

    public function test_compileData_with_http_and_group_emits_expected_snippets_and_collects_ids(): void
    {
        $data = [
            'group' => [
                'prefix' => 'api',
                'namePrefix' => 'admin',
            ],
            'routes' => [
                [
                    'type' => 'http',
                    'id' => 'r1',
                    'desc' => 'test route',
                    'method' => 'GET',
                    'path' => '/users',
                    'action' => 'App\\Http\\Controllers\\UserController@index',
                    'name' => 'users.index',
                    'domain' => 'api.example.com',
                    'prefix' => 'v1',
                    'where' => ['id' => '\\d+'],
                ],
            ],
        ];

        $out = $this->compiler()->compileData($data, 'C:\\src\\Routes.Web.Users.json');

        $this->assertSame(['r1'], $out['routeIds']);
        $this->assertSame('routes_web_users', $out['slug']);

        $php = $out['php'];
        $this->assertStringContainsString("->name('admin.')", $php, 'file-level namePrefix should be applied');
        $this->assertStringContainsString("->get('/users', ['App\\\\Http\\\\Controllers\\\\UserController', 'index'])", $php);
        $this->assertStringContainsString("->name('users.index')", $php);
        $this->assertStringContainsString("->domain('api.example.com')", $php);
        $this->assertStringContainsString("->prefix('v1')", $php);
        $this->assertStringContainsString("->where(", $php);
    }

    public function test_http_variants_any_and_match_and_action_expr_variants(): void
    {
        $data = [
            'routes' => [
                [
                    'type' => 'http',
                    'id' => 'any1',
                    'desc' => 'any method',
                    'method' => 'ANY',
                    'path' => '/any',
                    'action' => 'App\\Controller@handle',
                ],
                [
                    'type' => 'http',
                    'id' => 'match1',
                    'desc' => 'match verbs',
                    'method' => ['PUT', 'PATCH'],
                    'path' => '/item/{id}',
                    'action' => ['class' => 'App\\Handler', 'method' => 'update'],
                ],
                [
                    'type' => 'http',
                    'id' => 'classOnly',
                    'desc' => 'class only action',
                    'method' => 'POST',
                    'path' => '/post',
                    'action' => 'App\\Jobs\\DoThing',
                ],
            ],
        ];

        $php = $this->compiler()->compileData($data)['php'];

        // 1. Array-based action: Compiler produces ['App\Handler'::class, 'update']
        // Notice the quotes around the class name
        $this->assertStringContainsString("->match(['PUT', 'PATCH'], '/item/{id}', ['App\\\\Handler'::class, 'update'])", $php);

        // 2. Class-only action: Compiler produces 'App\Jobs\DoThing'::class
        $this->assertStringContainsString("->post('/post', 'App\\\\Jobs\\\\DoThing'::class)", $php);

        // 3. String-based action remains normal
        $this->assertStringContainsString("->any('/any', ['App\\\\Controller', 'handle'])", $php);
    }


    public function test_resource_compact_emits_expected_chain(): void
    {
        $data = [
            'routes' => [
                [
                    'type' => 'apiResource',
                    'id' => 'res1',
                    'desc' => 'api resource',
                    'name' => 'posts',
                    'controller' => 'App\\Http\\Controllers\\PostController',
                    'only' => ['index', 'show'],
                    'parameters' => ['posts' => 'article'],
                    'names' => ['index' => 'p.index'],
                    'shallow' => true,
                ],
            ],
        ];

        $php = $this->compiler()->compileData($data)['php'];
        $this->assertStringContainsString("->apiResource('posts', 'App\\\\Http\\\\Controllers\\\\PostController')", $php);
        $this->assertStringContainsString("->only(['index', 'show'])", $php);
        $this->assertStringContainsString("->parameters(array (", $php);
        $this->assertStringContainsString("'posts' => 'article'", $php);
        $this->assertStringContainsString("->names(array (", $php);
        $this->assertStringContainsString("'index' => 'p.index'", $php);
        $this->assertStringContainsString("->shallow()", $php);
    }

    /* -----------------------------------------------------------------
     |  REGISTRY FLOW TESTS (compileDataToRegistry) - The New Standard
     | -----------------------------------------------------------------
     */

    public function test_compileDataToRegistry_returns_atomic_entries(): void
    {
        $data = [
            'routes' => [
                [
                    'type' => 'http',
                    'id' => 'users.list',
                    'desc' => 'List users',
                    'method' => 'GET',
                    'path' => '/users',
                    'action' => 'App\\C@index',
                ],
                [
                    'type' => 'http',
                    'id' => 'users.create',
                    'desc' => 'Create user',
                    'method' => 'POST',
                    'path' => '/users',
                    'action' => 'App\\C@store',
                ]
            ]
        ];

        // Perform the compilation using the Registry method
        $result = $this->compiler()->compileDataToRegistry($data);

        // Assert 1: We got exactly 2 entries (Atomic check)
        $this->assertCount(2, $result['entries'], 'Registry should return one entry per route');
        $this->assertSame(['users.list', 'users.create'], $result['routeIds']);

        // Assert 2: Check the first entry structure
        $entry1 = $result['entries'][0];
        $this->assertSame('users.list', $entry1['id']);
        // Ensure it contains the PHP boilerplate required for independent file writing
        $this->assertStringContainsString('<?php', $entry1['content']);
        $this->assertStringContainsString('declare(strict_types=1);', $entry1['content']);
        $this->assertStringContainsString("->get('/users', ['App\\\\C', 'index'])", $entry1['content']);

        // Assert 3: Check the second entry
        $entry2 = $result['entries'][1];
        $this->assertSame('users.create', $entry2['id']);
        $this->assertStringContainsString("->post('/users', ['App\\\\C', 'store'])", $entry2['content']);
    }

    public function test_compileDataToRegistry_inherits_group_settings_in_isolated_entries(): void
    {
        $data = [
            'group' => [
                'prefix' => 'api/v1',
                'middleware' => ['api', 'auth'],
            ],
            'routes' => [
                [
                    'type' => 'http',
                    'id' => 'dashboard',
                    'desc' => 'Dash',
                    'method' => 'GET',
                    'path' => '/dashboard',
                    'action' => 'App\\Dash@show',
                    'middleware' => ['admin'], // Should merge with group
                ]
            ]
        ];

        $result = $this->compiler()->compileDataToRegistry($data);
        $content = $result['entries'][0]['content'];

        // Prefix check (usually applied via ->prefix() or baked into route path depending on implementation)
        // Assuming your compiler chains ->prefix('api/v1')
        $this->assertStringContainsString("->prefix('api/v1')", $content);

        // Middleware check (Group + Local)
        // Expected: ['api', 'auth', 'admin']
        $this->assertStringContainsString("->middleware(array (\n  0 => 'api',\n  1 => 'auth',\n  2 => 'admin',", $content);
    }

    /* -----------------------------------------------------------------
     |  ERROR HANDLING
     | -----------------------------------------------------------------
     */

    public function test_errors_are_thrown_for_invalid_inputs(): void
    {
        $this->expectException(RouteCompileException::class);
        $this->compiler()->compileData([]);
    }

    public function test_errors_node_missing_type(): void
    {
        $this->expectException(RouteCompileException::class);
        $this->compiler()->compileData(['routes' => [['id' => 'x', 'desc' => 'no type']]]);
    }
}