<?php

namespace Timeax\FortiPlugin\Core\Install;

use Illuminate\Support\Str;
use JsonException;
use Timeax\FortiPlugin\Core\Exceptions\RouteCompileException;
use Timeax\FortiPlugin\Support\MiddlewareNormalizer;

/**
 * Compile FortiPlugin route JSON into Route::* PHP.
 * Returns one chunk per source file:
 *   ['source'=>string, 'php'=>string, 'routeIds'=>string[]]
 */
final class JsonRouteCompiler
{
    /**
     * @param string[] $files
     * @return array<int, array{source:string, php:string, routeIds:string[]}>
     * @throws JsonException
     */
    public function compileFiles(array $files): array
    {
        $out = [];
        foreach ($files as $file) {
            $out[] = $this->compileFile($file);
        }
        return $out;
    }

    /**
     * @return array{source:string, php:string, routeIds:string[]}
     * @throws JsonException
     */
    public function compileFile(string $file): array
    {
        $json = @file_get_contents($file);
        if ($json === false) {
            throw new RouteCompileException("Cannot read route json: {$file}");
        }

        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new RouteCompileException("Invalid JSON in {$file}");
        }

        return $this->compileData($data, $file);
    }

    /**
     * @param array $data
     * @param string|null $source
     * @return array{source:string, php:string, routeIds:string[], slug:string}
     */
    public function compileData(array $data, ?string $source = null): array
    {
        $em = new PhpEmitter();
        $routeIds = [];

        $group = (array)($data['group'] ?? []);
        $routes = $data['routes'] ?? null;
        if (!is_array($routes) || $routes === []) {
            throw new RouteCompileException("Missing or empty 'routes' array" . ($source ? " in {$source}" : ''));
        }

        // Optional comment header (no <?php tag; RouteWriter will wrap)
        $em->line("/** FortiPlugin compiled routes " . ($source ? basename($source) : '') . " **/");

        // File-level group wrapper
        $this->emitGroupOpen($em, $group);

        foreach (array_values($routes) as $i => $node) {
            $this->emitNode($em, (array)$node, $group, $routeIds, "/routes[{$i}]");
        }

        // Close file-level group
        $this->emitGroupClose($em, $group);

        return [
            'source' => $source ?? '(inline)',
            'php' => $em->code(),
            'routeIds' => array_values(array_unique($routeIds)),
            'slug' => $source ? $this->slugFromPath($source) : 'inline',
        ];
    }

    private function slugFromPath(string $path): string
    {
        // Take the filename without extension (keeps dots like "web.posts.routes")
        $base = pathinfo($path, PATHINFO_FILENAME);

        // Convert any non-alphanumeric run to underscores, trim, then lower
        return (string)Str::of($base)
            ->replaceMatches('/[^A-Za-z0-9]+/', '_')
            ->trim('_')
            ->lower();
    }

    /* ========================= EMIT HELPERS ========================= */

    private function emitNode(PhpEmitter $em, array $node, array $inheritedGroup, array &$routeIds, string $jsonPath): void
    {
        $type = $node['type'] ?? null;
        if (!is_string($type)) {
            throw new RouteCompileException("Route node missing 'type' at {$jsonPath}");
        }

        if (!isset($node['id'], $node['desc']) || !is_string($node['id']) || !is_string($node['desc'])) {
            throw new RouteCompileException("Route node must include string 'id' and 'desc' at {$jsonPath}");
        }
        $routeIds[] = $node['id'];

        // Route-level guard may override group guard
        $routeGuard = $node['guard'] ?? null;

        switch ($type) {
            case 'group':
                $this->emitNestedGroup($em, $node, $inheritedGroup, $routeIds, $jsonPath);
                break;

            case 'http':
                $this->emitHttp($em, $node, $inheritedGroup, $routeGuard);
                break;

            case 'resource':
            case 'apiResource':
                $this->emitResource($em, $node, $inheritedGroup, $routeGuard);
                break;

            case 'redirect':
                $this->emitRedirect($em, $node, $inheritedGroup, $routeGuard);
                break;

            case 'view':
                $this->emitView($em, $node, $inheritedGroup, $routeGuard);
                break;

            case 'fallback':
                $this->emitFallback($em, $node, $inheritedGroup, $routeGuard);
                break;

            default:
                throw new RouteCompileException("Unknown route type '{$type}' at {$jsonPath}");
        }
    }

    private function emitGroupOpen(PhpEmitter $em, array $group): void
    {
        if ($group === []) return;
        $em->open($this->startChain($group) . '->group(function () {');
    }

    private function emitGroupClose(PhpEmitter $em, array $group): void
    {
        if ($group === []) return;
        $em->close('});');
    }

    private function emitNestedGroup(PhpEmitter $em, array $node, array $inheritedGroup, array &$routeIds, string $jsonPath): void
    {
        $group = (array)($node['group'] ?? []);
        $merged = $this->mergeGroups($inheritedGroup, $group);

        $em->open($this->startChain($merged) . '->group(function () {');

        foreach (array_values((array)($node['routes'] ?? [])) as $i => $child) {
            $this->emitNode($em, (array)$child, $merged, $routeIds, "{$jsonPath}/routes[{$i}]");
        }

        $em->close('});');
    }

    private function emitHttp(PhpEmitter $em, array $node, array $group, ?string $routeGuard): void
    {
        $method = $node['method'] ?? null;
        $path = $node['path'] ?? null;
        $action = $node['action'] ?? null;

        if ($path === null || $action === null || $method === null) {
            throw new RouteCompileException("HTTP route requires 'method','path','action'");
        }

        [$chain, $mw, $name, $where, $domain, $prefix] = $this->commonProps($node, $group, $routeGuard);

        $methodCall = $this->methodCallFor($method, $path, $action);
        $suffix = $this->tail($name, $mw, $where, $domain, $prefix);

        $em->line($chain . '->' . $methodCall . $suffix . ';');
    }

    private function emitResource(PhpEmitter $em, array $node, array $group, ?string $routeGuard): void
    {
        $type = $node['type'];                // 'resource' | 'apiResource'
        $resource = $node['name'] ?? null;        // e.g. 'posts'
        $controller = $node['controller'] ?? null;  // FQCN
        if (!$resource || !$controller) {
            throw new RouteCompileException("Resource route requires 'name' and 'controller'");
        }

        [$chain, $mw, $baseName, $where, $domain, $prefix] =
            $this->commonProps($node, $group, $routeGuard);

        // If constraints exist, expand into explicit routes to support ->where()
        if (!empty($where)) {
            $this->emitResourceExpanded($em, $type, $resource, $controller, $chain, $mw, $baseName, (array)$where, $domain, $prefix, $node);
            return;
        }

        // Compact form (no where constraints)
        $call = $type === 'apiResource'
            ? "apiResource(" . $this->s($resource) . ', ' . $this->s($controller) . ')'
            : "resource(" . $this->s($resource) . ', ' . $this->s($controller) . ')';

        $em->open($chain . '->' . $call);
        if (!empty($node['only'])) $em->line("->only(" . $this->exportArraySimple($node['only']) . ")");
        if (!empty($node['except'])) $em->line("->except(" . $this->exportArraySimple($node['except']) . ")");
        if (!empty($node['parameters'])) $em->line("->parameters(" . var_export((array)$node['parameters'], true) . ")");
        if (!empty($node['names'])) $em->line("->names(" . var_export((array)$node['names'], true) . ")");
        if (!empty($node['shallow'])) $em->line("->shallow()");
        foreach ($this->tailParts($baseName, $mw, null, $domain, $prefix) as $part) {
            $em->line($part);
        }
        $em->close(';');
    }

    /**
     * Expand a resource/apiResource so we can apply ->where().
     * Supports: only/except, names, parameters (shallow omitted here).
     */
    private function emitResourceExpanded(
        PhpEmitter $em,
        string     $type,              // 'resource' | 'apiResource'
        string     $resource,          // e.g. 'posts'
        string     $controller,        // FQCN
        string     $chain,             // starting Route chain
        array      $mw,                 // normalized middleware
        ?string    $baseName,         // base name for ->name(...)
        array      $where,              // constraints per route
        ?string    $domain,
        ?string    $prefix,
        array      $node                // original node (for only/except/names/parameters)
    ): void
    {
        $isApi = ($type === 'apiResource');

        // Actions
        $all = $isApi
            ? ['index', 'store', 'show', 'update', 'destroy']
            : ['index', 'create', 'store', 'show', 'edit', 'update', 'destroy'];

        $only = isset($node['only']) ? array_values((array)$node['only']) : null;
        $except = isset($node['except']) ? array_values((array)$node['except']) : null;

        $actions = $all;
        if ($only) $actions = array_values(array_intersect($actions, $only));
        if ($except) $actions = array_values(array_diff($actions, $except));

        // Parameter name
        $paramMap = (array)($node['parameters'] ?? []);
        $paramName = $paramMap[$resource] ?? Str::singular($resource);

        // Names override
        $names = (array)($node['names'] ?? []);
        $base = $baseName ?: $resource;

        foreach ($actions as $action) {
            $path = $this->resourcePath($resource, $paramName, $action);
            $method = $this->resourceVerb($action);
            $actRef = $controller . '@' . $this->resourceControllerMethod($action);

            $rname = $names[$action] ?? ($base ? "{$base}.{$action}" : null);
            $suffix = $this->tail($rname, $mw, $where, $domain, $prefix);

            $em->line($chain . '->' . $this->methodCallFor($method, $path, $actRef) . $suffix . ';');
        }
    }

    /** Resolve URI for a given resource action */
    private function resourcePath(string $resource, string $param, string $action): string
    {
        return match ($action) {
            'create' => "/{$resource}/create",
            'show', 'destroy', 'update' => "/{$resource}/{{$param}}",
            'edit' => "/{$resource}/{{$param}}/edit",
            default => "/{$resource}",
        };
    }

    /** Resolve HTTP verb(s) for a given resource action */
    private function resourceVerb(string $action): string|array
    {
        return match ($action) {
            'store' => 'POST',
            'update' => ['PUT', 'PATCH'],
            'destroy' => 'DELETE',
            default => 'GET',
        };
    }

    /** Resolve controller method name for a given resource action */
    private function resourceControllerMethod(string $action): string
    {
        return $action; // Laravel defaults
    }

    private function emitRedirect(PhpEmitter $em, array $node, array $group, ?string $routeGuard): void
    {
        $path = $node['path'] ?? null;
        $to = $node['to'] ?? null;
        $status = $node['status'] ?? 302;
        if (!$path || !$to) {
            throw new RouteCompileException("Redirect route requires 'path' and 'to'");
        }

        // ignore where for redirects
        [$chain, $mw, $name, , $domain, $prefix] = $this->commonProps($node, $group, $routeGuard);
        $suffix = $this->tail($name, $mw, null, $domain, $prefix);

        $em->line($chain . '->redirect(' . $this->s($path) . ', ' . $this->s($to) . ', ' . (int)$status . ')' . $suffix . ';');
    }

    private function emitView(PhpEmitter $em, array $node, array $group, ?string $routeGuard): void
    {
        $path = $node['path'] ?? null;
        $view = $node['view'] ?? null;
        $data = $node['data'] ?? [];
        if (!$path || !$view) {
            throw new RouteCompileException("View route requires 'path' and 'view'");
        }

        // ignore where for views
        [$chain, $mw, $name, , $domain, $prefix] = $this->commonProps($node, $group, $routeGuard);
        $suffix = $this->tail($name, $mw, null, $domain, $prefix);

        $em->line($chain . '->view(' . $this->s($path) . ', ' . $this->s($view) . ', ' . var_export((array)$data, true) . ')' . $suffix . ';');
    }

    private function emitFallback(PhpEmitter $em, array $node, array $group, ?string $routeGuard): void
    {
        [$chain, $mw, $name] = $this->commonProps($node, $group, $routeGuard);

        $action = $node['action'] ?? null;
        if (!$action) {
            throw new RouteCompileException("Fallback route requires 'action'");
        }

        $suffix = $this->tail($name, $mw, null, null, null);
        $em->line($chain . '->fallback(' . $this->actionExpr($action) . ')' . $suffix . ';');
    }

    /* ========================= UTILITIES ========================= */

    private function mergeGroups(array $a, array $b): array
    {
        // Override: prefix/domain/namePrefix/guard; append middleware
        $out = $a;
        foreach (['prefix', 'domain', 'namePrefix', 'guard'] as $k) {
            if (array_key_exists($k, $b)) $out[$k] = $b[$k];
        }
        $mwA = (array)($a['middleware'] ?? []);
        $mwB = (array)($b['middleware'] ?? []);
        if ($mwA || $mwB) $out['middleware'] = array_values(array_merge($mwA, $mwB));
        return $out;
    }

    private function getChain(array $group): string
    {
        $chain = 'Route';
        if (!empty($group['domain'])) $chain .= '->domain(' . $this->s($group['domain']) . ')';
        if (!empty($group['prefix'])) $chain .= '->prefix(' . $this->s($group['prefix']) . ')';
        return $chain;
    }

    private function startChain(array $group): string
    {
        $chain = $this->getChain($group);

        // Normalize middleware with guard
        $mw = MiddlewareNormalizer::normalize($group['guard'] ?? null, null, (array)($group['middleware'] ?? []));
        if ($mw) $chain .= '->middleware(' . $this->exportArraySimple($mw) . ')';

        if (!empty($group['namePrefix'])) {
            $np = (string)$group['namePrefix'];
            if ($np !== '' && !str_ends_with($np, '.')) $np .= '.';
            $chain .= '->name(' . $this->s($np) . ')';
        }

        return $chain;
    }

    /**
     * @return array{0:string,1:array,2:?string,3:?array,4:?string,5:?string}
     */
    private function commonProps(array $node, array $group, ?string $routeGuard): array
    {
        $chain = $this->getChain($group);

        // Middleware normalization (route-level guard may override group guard)
        $mw = MiddlewareNormalizer::normalize($group['guard'] ?? null, $routeGuard, (array)($node['middleware'] ?? []));

        $name = $node['name'] ?? null;
        $where = $node['where'] ?? null;
        $domain = $node['domain'] ?? null;
        $prefix = $node['prefix'] ?? null;

        return [$chain, $mw, $name, $where, $domain, $prefix];
    }

    private function tail(?string $name, array $mw, ?array $where, ?string $domain, ?string $prefix): string
    {
        $parts = $this->tailParts($name, $mw, $where, $domain, $prefix);
        return $parts ? implode('', $parts) : '';
    }

    /** @return string[] */
    private function tailParts(?string $name, array $mw, ?array $where, ?string $domain, ?string $prefix): array
    {
        $parts = [];
        if ($mw) $parts[] = '->middleware(' . $this->exportArraySimple($mw) . ')';
        if ($name) $parts[] = '->name(' . $this->s($name) . ')';
        if ($where) $parts[] = '->where(' . var_export($where, true) . ')';
        if ($domain) $parts[] = '->domain(' . $this->s($domain) . ')';
        if ($prefix) $parts[] = '->prefix(' . $this->s($prefix) . ')';
        return $parts;
    }

    private function methodCallFor(string|array $method, string $path, string|array $action): string
    {
        if (is_array($method)) {
            $verbs = array_values(array_map('strtoupper', array_map('strval', $method)));
            return 'match(' . $this->exportArraySimple($verbs) . ', ' . $this->s($path) . ', ' . $this->actionExpr($action) . ')';
        }
        $verb = strtoupper($method);
        if ($verb === 'ANY') {
            return 'any(' . $this->s($path) . ', ' . $this->actionExpr($action) . ')';
        }
        $lower = strtolower($verb); // GET|POST|PUT|PATCH|DELETE|OPTIONS|HEAD
        return "{$lower}(" . $this->s($path) . ', ' . $this->actionExpr($action) . ')';
    }

    private function actionExpr(string|array $action): string
    {
        if (is_string($action)) {
            if (str_contains($action, '@')) {
                [$class, $method] = explode('@', $action, 2);
                return '[' . $this->s($class) . ', ' . $this->s($method) . ']';
            }
            return $this->s($action) . '::class';
        }

        $class = $action['class'] ?? null;
        if (!$class) {
            throw new RouteCompileException("ControllerRef requires 'class'");
        }
        $method = $action['method'] ?? null;

        return $method
            ? '[' . $this->s($class) . '::class, ' . $this->s($method) . ']'
            : $this->s($class) . '::class';
    }

    private function exportArraySimple(array $arr): string
    {
        return '[' . implode(', ', array_map([$this, 's'], array_values($arr))) . ']';
    }

    private function s(string $value): string
    {
        return var_export($value, true);
    }
}