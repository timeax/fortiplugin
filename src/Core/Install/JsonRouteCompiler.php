<?php /** @noinspection GrazieInspection */
declare(strict_types=1);

namespace Timeax\FortiPlugin\Core\Install;

use Illuminate\Support\Str;
use JsonException;
use Timeax\FortiPlugin\Core\Exceptions\RouteCompileException;
use Timeax\FortiPlugin\Support\MiddlewareNormalizer;

/**
 * Compile FortiPlugin route JSON.
 *
 * Legacy (compat):
 *   compileFiles() → array<int, { source, php, routeIds, slug }>
 *
 * Registry-first (new):
 *   compileFileToRegistry() → { entries: list<{ route, id, content, file }>, routeIds: list<string> }
 *   compileDataToRegistry() → same
 */
final class JsonRouteCompiler
{
    /**
     * @param string[] $files
     * @return array<int, array{source:string, php:string, routeIds:string[], slug:string}>
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
     * @return array{source:string, php:string, routeIds:string[], slug:string}
     * @throws JsonException
     */
    public function compileFile(string $file): array
    {
        $json = @file_get_contents($file);
        if ($json === false) {
            throw new RouteCompileException("Cannot read route json: $file");
        }

        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new RouteCompileException("Invalid JSON in $file");
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

        $group  = (array)($data['group'] ?? []);
        $routes = $data['routes'] ?? null;
        if (!is_array($routes) || $routes === []) {
            throw new RouteCompileException("Missing or empty 'routes' array" . ($source ? " in $source" : ''));
        }

        // Optional comment header (no <?php tag; RouteWriter/Materializer will wrap if needed)
        $em->line("/** FortiPlugin compiled routes " . ($source ? basename($source) : '') . " **/");

        // File-level group wrapper
        $this->emitGroupOpen($em, $group);

        foreach (array_values($routes) as $i => $node) {
            $this->emitNode($em, (array)$node, $group, $routeIds, "/routes[$i]");
        }

        // Close file-level group
        $this->emitGroupClose($em, $group);

        return [
            'source'   => $source ?? '(inline)',
            'php'      => $em->code(),
            'routeIds' => array_values(array_unique($routeIds)),
            'slug'     => $source ? $this->slugFromPath($source) : 'inline',
        ];
    }

    /* ───────────────────── Registry-first API ───────────────────── */

    /**
     * Build one registry entry per terminal route id (resources become a single entry with route:string[]).
     * @return array{entries: list<array{route:string|array, id:string, content:string, file:string}>, routeIds:list<string>}
     * @throws JsonException
     */
    public function compileFileToRegistry(string $file): array
    {
        $json = @file_get_contents($file);
        if ($json === false) {
            throw new RouteCompileException("Cannot read route json: $file");
        }
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new RouteCompileException("Invalid JSON in $file");
        }
        return $this->compileDataToRegistry($data, $file);
    }

    /**
     * @return array{entries: list<array{route:string|array, id:string, content:string, file:string}>, routeIds:list<string>}
     */
    public function compileDataToRegistry(array $data, ?string $source = null): array
    {
        $entries  = [];
        $routeIds = [];

        $group  = (array)($data['group'] ?? []);
        $routes = $data['routes'] ?? null;
        if (!is_array($routes) || $routes === []) {
            throw new RouteCompileException("Missing or empty 'routes' array" . ($source ? " in $source" : ''));
        }

        $collect = function (array $node, array $inheritedGroup, string $jsonPath) use (&$entries, &$routeIds, &$collect, $source): void {
            $type = $node['type'] ?? null;
            if (!is_string($type)) {
                throw new RouteCompileException("Route node missing 'type' at $jsonPath");
            }

            if ($type === 'group') {
                $merged = $this->mergeGroups($inheritedGroup, (array)($node['group'] ?? []));
                foreach ((array)($node['routes'] ?? []) as $i => $child) {
                    $collect((array)$child, $merged, "$jsonPath/routes[$i]");
                }
                return;
            }

            if (!isset($node['id'], $node['desc']) || !is_string($node['id']) || !is_string($node['desc'])) {
                throw new RouteCompileException("Route node must include string 'id' and 'desc' at $jsonPath");
            }

            $id        = $node['id'];
            $routeIds[] = $id;
            $guard     = $node['guard'] ?? null;

            $contentLines = [];
            $routesForId  = [];

            $emitOne = static function (string $codeLine) use (&$contentLines): void {
                $contentLines[] = $codeLine;
            };

            switch ($type) {
                case 'http': {
                    $method = $node['method'] ?? null;
                    $path   = $node['path'] ?? null;
                    $action = $node['action'] ?? null;
                    if ($path === null || $action === null || $method === null) {
                        throw new RouteCompileException("HTTP route requires 'method','path','action' at $jsonPath");
                    }
                    [$chain,$mw,$name,$where,$domain,$prefix] = $this->commonProps($node, $inheritedGroup, $guard);
                    $emitOne($chain . '->' . $this->methodCallFor($method, $path, $action) . $this->tail($name,$mw,$where,$domain,$prefix) . ';');
                    $routesForId = $path;
                    break;
                }
                case 'redirect': {
                    $path = $node['path'] ?? null;
                    $to   = $node['to'] ?? null;
                    $status = (int)($node['status'] ?? 302);
                    if (!$path || !$to) throw new RouteCompileException("Redirect requires 'path' and 'to' at $jsonPath");
                    [$chain,$mw,$name,, $domain,$prefix] = $this->commonProps($node,$inheritedGroup,$guard);
                    $emitOne($chain . '->redirect(' . $this->s($path) . ', ' . $this->s($to) . ', ' . $status . ')' . $this->tail($name,$mw,null,$domain,$prefix) . ';');
                    $routesForId = $path;
                    break;
                }
                case 'view': {
                    $path = $node['path'] ?? null;
                    $view = $node['view'] ?? null;
                    $data = (array)($node['data'] ?? []);
                    if (!$path || !$view) throw new RouteCompileException("View requires 'path' and 'view' at $jsonPath");
                    [$chain,$mw,$name,, $domain,$prefix] = $this->commonProps($node,$inheritedGroup,$guard);
                    $emitOne($chain . '->view(' . $this->s($path) . ', ' . $this->s($view) . ', ' . var_export($data, true) . ')' . $this->tail($name,$mw,null,$domain,$prefix) . ';');
                    $routesForId = $path;
                    break;
                }
                case 'fallback': {
                    [$chain,$mw,$name] = $this->commonProps($node,$inheritedGroup,$guard);
                    $action = $node['action'] ?? null;
                    if (!$action) throw new RouteCompileException("Fallback requires 'action' at $jsonPath");
                    $emitOne($chain . '->fallback(' . $this->actionExpr($action) . ')' . $this->tail($name,$mw,null,null,null) . ';');
                    $routesForId = '__fallback__';
                    break;
                }
                case 'resource':
                case 'apiResource': {
                    $resource   = $node['name'] ?? null;
                    $controller = $node['controller'] ?? null;
                    if (!$resource || !$controller) {
                        throw new RouteCompileException("Resource requires 'name' and 'controller' at $jsonPath");
                    }
                    [$chain,$mw,$baseName,$where,$domain,$prefix] = $this->commonProps($node,$inheritedGroup,$guard);

                    $paths = [];
                    if (!empty($where)) {
                        $isApi = ($type === 'apiResource');
                        $all = $isApi
                            ? ['index','store','show','update','destroy']
                            : ['index','create','store','show','edit','update','destroy'];

                        $only   = isset($node['only'])   ? array_values((array)$node['only'])   : null;
                        $except = isset($node['except']) ? array_values((array)$node['except']) : null;
                        $actions = $all;
                        if ($only)   $actions = array_values(array_intersect($actions, $only));
                        if ($except) $actions = array_values(array_diff($actions, $except));

                        $paramMap = (array)($node['parameters'] ?? []);
                        $param    = $paramMap[$resource] ?? Str::singular($resource);
                        $names    = (array)($node['names'] ?? []);
                        $base     = $baseName ?: $resource;

                        foreach ($actions as $action) {
                            $path  = $this->resourcePath($resource, $param, $action);
                            $verb  = $this->resourceVerb($action);
                            $act   = $controller . '@' . $this->resourceControllerMethod($action);
                            $rname = $names[$action] ?? ($base ? "$base.$action" : null);
                            $emitOne($chain . '->' . $this->methodCallFor($verb, $path, $act) . $this->tail($rname,$mw,(array)$where,$domain,$prefix) . ';');
                            $paths[] = $path;
                        }
                    } else {
                        $call = $type === 'apiResource'
                            ? "apiResource(" . $this->s($resource) . ', ' . $this->s($controller) . ')'
                            : "resource(" . $this->s($resource) . ', ' . $this->s($controller) . ')';

                        $line = $chain . '->' . $call;
                        if (!empty($node['only']))        $line .= "->only(" . $this->exportArraySimple($node['only']) . ")";
                        if (!empty($node['except']))      $line .= "->except(" . $this->exportArraySimple($node['except']) . ")";
                        if (!empty($node['parameters']))  $line .= "->parameters(" . var_export((array)$node['parameters'], true) . ")";
                        if (!empty($node['names']))       $line .= "->names(" . var_export((array)$node['names'], true) . ")";
                        if (!empty($node['shallow']))     $line .= "->shallow()";
                        foreach ($this->tailParts($baseName,$mw,null,$domain,$prefix) as $part) {
                            $line .= $part;
                        }
                        $emitOne($line . ';');

                        $isApi = ($type === 'apiResource');
                        $all   = $isApi
                            ? ['index','store','show','update','destroy']
                            : ['index','create','store','show','edit','update','destroy'];
                        $param = Str::singular($resource);
                        foreach ($all as $action) {
                            $paths[] = $this->resourcePath($resource, $param, $action);
                        }
                    }

                    $routesForId = $paths;
                    break;
                }

                default:
                    throw new RouteCompileException("Unknown route type '$type' at $jsonPath");
            }

            $php = [];
            $php[] = "<?php";
            $php[] = "declare(strict_types=1);";
            $php[] = "/** compiled unit for route id: $id" . ($source ? " (source: ".basename($source).")" : "") . " */";
            $php[] = "use Illuminate\\Support\\Facades\\Route;";
            $php[] = "";
            foreach ($contentLines as $ln) $php[] = $ln;
            $php[] = "";

            $entries[] = [
                'route'   => $routesForId,
                'id'      => $id,
                'content' => implode("\n", $php),
                'file'    => $this->fileNameForId($id),
            ];
        };

        foreach ($routes as $i => $node) {
            $collect((array)$node, $group, "/routes[$i]");
        }

        return [
            'entries'  => $entries,
            'routeIds' => array_values(array_unique($routeIds)),
        ];
    }

    private function fileNameForId(string $id): string
    {
        $name = (string) Str::of($id)->replaceMatches('/[^A-Za-z0-9_.-]+/', '_')->trim('_')->lower();
        if ($name === '') $name = 'route';
        if (!str_ends_with($name, '.php')) $name .= '.php';
        return $name;
    }

    /* ========================= EMIT HELPERS ========================= */

    private function emitNode(PhpEmitter $em, array $node, array $inheritedGroup, array &$routeIds, string $jsonPath): void
    {
        $type = $node['type'] ?? null;
        if (!is_string($type)) {
            throw new RouteCompileException("Route node missing 'type' at $jsonPath");
        }

        if (!isset($node['id'], $node['desc']) || !is_string($node['id']) || !is_string($node['desc'])) {
            throw new RouteCompileException("Route node must include string 'id' and 'desc' at $jsonPath");
        }
        $routeIds[] = $node['id'];

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
                throw new RouteCompileException("Unknown route type '$type' at $jsonPath");
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
        $group  = (array)($node['group'] ?? []);
        $merged = $this->mergeGroups($inheritedGroup, $group);

        $em->open($this->startChain($merged) . '->group(function () {');

        foreach (array_values((array)($node['routes'] ?? [])) as $i => $child) {
            $this->emitNode($em, (array)$child, $merged, $routeIds, "$jsonPath/routes[$i]");
        }

        $em->close('});');
    }

    private function emitHttp(PhpEmitter $em, array $node, array $group, ?string $routeGuard): void
    {
        $method = $node['method'] ?? null;
        $path   = $node['path'] ?? null;
        $action = $node['action'] ?? null;

        if ($path === null || $action === null || $method === null) {
            throw new RouteCompileException("HTTP route requires 'method','path','action'");
        }

        [$chain, $mw, $name, $where, $domain, $prefix] = $this->commonProps($node, $group, $routeGuard);
        $methodCall = $this->methodCallFor($method, $path, $action);
        $suffix     = $this->tail($name, $mw, $where, $domain, $prefix);

        $em->line($chain . '->' . $methodCall . $suffix . ';');
    }

    private function emitResource(PhpEmitter $em, array $node, array $group, ?string $routeGuard): void
    {
        $type       = $node['type'];
        $resource   = $node['name'] ?? null;
        $controller = $node['controller'] ?? null;
        if (!$resource || !$controller) {
            throw new RouteCompileException("Resource route requires 'name' and 'controller'");
        }

        [$chain, $mw, $baseName, $where, $domain, $prefix] = $this->commonProps($node, $group, $routeGuard);

        if (!empty($where)) {
            $this->emitResourceExpanded($em, $type, $resource, $controller, $chain, $mw, $baseName, (array)$where, $domain, $prefix, $node);
            return;
        }

        $call = $type === 'apiResource'
            ? "apiResource(" . $this->s($resource) . ', ' . $this->s($controller) . ')'
            : "resource(" . $this->s($resource) . ', ' . $this->s($controller) . ')';

        $em->open($chain . '->' . $call);
        if (!empty($node['only']))       $em->line("->only(" . $this->exportArraySimple($node['only']) . ")");
        if (!empty($node['except']))     $em->line("->except(" . $this->exportArraySimple($node['except']) . ")");
        if (!empty($node['parameters'])) $em->line("->parameters(" . var_export((array)$node['parameters'], true) . ")");
        if (!empty($node['names']))      $em->line("->names(" . var_export((array)$node['names'], true) . ")");
        if (!empty($node['shallow']))    $em->line("->shallow()");
        foreach ($this->tailParts($baseName, $mw, null, $domain, $prefix) as $part) {
            $em->line($part);
        }
        $em->close(';');
    }

    private function emitResourceExpanded(
        PhpEmitter $em,
        string     $type,
        string     $resource,
        string     $controller,
        string     $chain,
        array      $mw,
        ?string    $baseName,
        array      $where,
        ?string    $domain,
        ?string    $prefix,
        array      $node
    ): void {
        $isApi = ($type === 'apiResource');
        $all   = $isApi
            ? ['index','store','show','update','destroy']
            : ['index','create','store','show','edit','update','destroy'];

        $only   = isset($node['only'])   ? array_values((array)$node['only'])   : null;
        $except = isset($node['except']) ? array_values((array)$node['except']) : null;
        $actions = $all;
        if ($only)   $actions = array_values(array_intersect($actions, $only));
        if ($except) $actions = array_values(array_diff($actions, $except));

        $paramMap = (array)($node['parameters'] ?? []);
        $param    = $paramMap[$resource] ?? Str::singular($resource);
        $names    = (array)($node['names'] ?? []);
        $base     = $baseName ?: $resource;

        foreach ($actions as $action) {
            $path  = $this->resourcePath($resource, $param, $action);
            $verb  = $this->resourceVerb($action);
            $act   = $controller . '@' . $this->resourceControllerMethod($action);
            $rname = $names[$action] ?? ($base ? "$base.$action" : null);
            $em->line($chain . '->' . $this->methodCallFor($verb, $path, $act) . $this->tail($rname,$mw,$where,$domain,$prefix) . ';');
        }
    }

    /** Resolve URI for a given resource action */
    private function resourcePath(string $resource, string $param, string $action): string
    {
        return match ($action) {
            'create' => "/$resource/create",
            'show', 'destroy', 'update' => "/$resource/{{$param}}",
            'edit' => "/$resource/{{$param}}/edit",
            default => "/$resource",
        };
    }

    /** Resolve HTTP verb(s) for a given resource action */
    private function resourceVerb(string $action): string|array
    {
        return match ($action) {
            'store'   => 'POST',
            'update'  => ['PUT', 'PATCH'],
            'destroy' => 'DELETE',
            default   => 'GET',
        };
    }

    /** Resolve controller method name for a given resource action */
    private function resourceControllerMethod(string $action): string
    {
        return $action;
    }

    private function emitRedirect(PhpEmitter $em, array $node, array $group, ?string $routeGuard): void
    {
        $path   = $node['path'] ?? null;
        $to     = $node['to'] ?? null;
        $status = $node['status'] ?? 302;
        if (!$path || !$to) {
            throw new RouteCompileException("Redirect route requires 'path' and 'to'");
        }
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

        $mw = MiddlewareNormalizer::normalize($group['guard'] ?? null, $routeGuard, (array)($node['middleware'] ?? []));

        $name   = $node['name'] ?? null;
        $where  = $node['where'] ?? null;
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
        if ($mw)    $parts[] = '->middleware(' . $this->exportArraySimple($mw) . ')';
        if ($name)  $parts[] = '->name(' . $this->s($name) . ')';
        if ($where) $parts[] = '->where(' . var_export($where, true) . ')';
        if ($domain)$parts[] = '->domain(' . $this->s($domain) . ')';
        if ($prefix)$parts[] = '->prefix(' . $this->s($prefix) . ')';
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
        $lower = strtolower($verb);
        return "$lower(" . $this->s($path) . ', ' . $this->actionExpr($action) . ')';
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

    private function slugFromPath(string $path): string
    {
        $base = pathinfo($path, PATHINFO_FILENAME);
        return (string)Str::of($base)->replaceMatches('/[^A-Za-z0-9]+/', '_')->trim('_')->lower();
    }
}