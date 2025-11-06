<?php

declare(strict_types=1);

namespace Tests\Core\Install;

use PHPUnit\Framework\TestCase;
use Timeax\FortiPlugin\Core\Install\JsonRouteCompiler;
use Timeax\FortiPlugin\Core\Exceptions\RouteCompileException;

final class JsonRouteCompilerTest extends TestCase
{
    private function compiler(): JsonRouteCompiler
    {
        return new JsonRouteCompiler();
    }

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
        $this->assertStringContainsString("->where(", $php); // format from var_export is environment-dependent; just check presence
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
                    'action' => 'App\\Jobs\\DoThing', // should become App\\Jobs\\DoThing::class
                ],
            ],
        ];

        $php = $this->compiler()->compileData($data)['php'];
        $this->assertStringContainsString("->any('/any', ['App\\\\Controller', 'handle'])", $php);
        $this->assertStringContainsString("->match(['PUT', 'PATCH'], '/item/{id}', [App\\\\Handler::class, 'update'])", $php);
        $this->assertStringContainsString("->post('/post', App\\\\Jobs\\\\DoThing::class)", $php);
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

    public function test_resource_with_where_is_expanded_with_verbs_and_where(): void
    {
        $data = [
            'routes' => [
                [
                    'type' => 'resource',
                    'id' => 'res2',
                    'desc' => 'resource expanded',
                    'name' => 'photos',
                    'controller' => 'App\\Http\\Controllers\\PhotoController',
                    'where' => ['photo' => '[0-9]+'],
                    'names' => [
                        'show' => 'photos.show.alias'
                    ],
                ],
            ],
        ];

        $php = $this->compiler()->compileData($data)['php'];
        // index
        $this->assertStringContainsString("->get('/photos', ['App\\\\Http\\\\Controllers\\\\PhotoController', 'index'])", $php);
        // create
        $this->assertStringContainsString("->get('/photos/create', ['App\\\\Http\\\\Controllers\\\\PhotoController', 'create'])", $php);
        // show with alias name
        $this->assertStringContainsString("->get('/photos/{photo}', ['App\\\\Http\\\\Controllers\\\\PhotoController', 'show'])->name('photos.show.alias')", $php);
        // update must be match PUT/PATCH
        $this->assertStringContainsString("->match(['PUT', 'PATCH'], '/photos/{photo}', ['App\\\\Http\\\\Controllers\\\\PhotoController', 'update'])", $php);
        // where applied to each expanded route
        $this->assertStringContainsString("->where(array (\n  'photo' => '[0-9]+'", $php);
    }

    public function test_redirect_view_and_fallback(): void
    {
        $data = [
            'routes' => [
                [
                    'type' => 'redirect',
                    'id' => 'redir1',
                    'desc' => 'redir',
                    'path' => '/old',
                    'to' => '/new',
                    'status' => 301,
                    'name' => 'redir',
                ],
                [
                    'type' => 'view',
                    'id' => 'view1',
                    'desc' => 'view',
                    'path' => '/welcome',
                    'view' => 'welcome',
                    'data' => ['a' => 1],
                    'name' => 'welcome',
                ],
                [
                    'type' => 'fallback',
                    'id' => 'fb',
                    'desc' => 'fb',
                    'action' => 'App\\Http\\Controllers\\FallbackController@handle',
                    'name' => 'fb.name',
                ],
            ],
        ];

        $php = $this->compiler()->compileData($data)['php'];
        $this->assertStringContainsString("->redirect('/old', '/new', 301)->name('redir')", $php);
        $this->assertStringContainsString("->view('/welcome', 'welcome', array (\n  'a' => 1,\n))", $php);
        $this->assertStringContainsString("->fallback(['App\\\\Http\\\\Controllers\\\\FallbackController', 'handle'])->name('fb.name')", $php);
    }

    public function test_compileFile_reads_and_compiles_json(): void
    {
        $json = json_encode([
            'routes' => [
                [
                    'type' => 'http',
                    'id' => 'r',
                    'desc' => 'from file',
                    'method' => 'GET',
                    'path' => '/',
                    'action' => 'App\\C@i',
                ]
            ]
        ], JSON_THROW_ON_ERROR);

        $tmp = tempnam(sys_get_temp_dir(), 'routes_');
        file_put_contents($tmp, $json);

        try {
            $out = $this->compiler()->compileFile($tmp);
            $this->assertSame([$tmp, true], [$out['source'], str_contains($out['php'], "->get('/', ['App\\\\C', 'i'])")]);
            $this->assertSame(['r'], $out['routeIds']);
        } finally {
            @unlink($tmp);
        }
    }

    public function test_errors_are_thrown_for_invalid_inputs(): void
    {
        // missing routes
        $this->expectException(RouteCompileException::class);
        $this->compiler()->compileData([]);
    }

    public function test_errors_node_missing_type(): void
    {
        $this->expectException(RouteCompileException::class);
        $this->compiler()->compileData(['routes' => [['id' => 'x', 'desc' => 'no type']]]);
    }

    public function test_errors_node_missing_id_or_desc(): void
    {
        $this->expectException(RouteCompileException::class);
        $this->compiler()->compileData(['routes' => [['type' => 'http']]]);
    }

    public function test_errors_redirect_missing_to(): void
    {
        $this->expectException(RouteCompileException::class);
        $this->compiler()->compileData([
            'routes' => [[
                'type' => 'redirect',
                'id' => 'x',
                'desc' => 'x',
                'path' => '/a',
            ]]
        ]);
    }
}
