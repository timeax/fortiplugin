<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Contracts;

use RuntimeException;

/**
 * Global route registry to ensure uniqueness and stage routes for later activation.
 *
 * The installer only validates and queues; Activator applies them at runtime.
 *
 * @phpstan-type HttpVerb 'GET'|'POST'|'PUT'|'PATCH'|'DELETE'|'OPTIONS'|'HEAD'|'ANY'|'MATCH'
 *
 * @phpstan-type ControllerRef array{
 *   class: non-empty-string,                # FQCN under <psr4_root>\<Placeholder.name>\...
 *   method?: non-empty-string               # optional for invokable controllers in 'http'/'fallback'
 * }
 *
 * @phpstan-type WhereConstraints array<string, non-empty-string>  # { param: "PCRE-fragment" }
 *
 * @phpstan-type GroupOptions array{
 *   prefix?: string,
 *   namePrefix?: string,
 *   domain?: string,
 *   middleware?: list<non-empty-string>
 * }
 *
 * @phpstan-type BaseRouteProps array{
 *   id: non-empty-string,
 *   desc: non-empty-string,
 *   name?: string,
 *   middleware?: list<non-empty-string>,
 *   where?: WhereConstraints,
 *   domain?: string,
 *   prefix?: string
 * }
 *
 * # --- Node cores ---
 *
 * @phpstan-type HttpRouteCore array{
 *   type: 'http',
 *   method: HttpVerb|non-empty-list<HttpVerb>,   # 'ANY' ok; 'MATCH' must not be used alone
 *   path: non-empty-string,
 *   action: ControllerRef|non-empty-string       # "Class@method" string or object form
 * }
 * @phpstan-type HttpRoute array{ }&BaseRouteProps&HttpRouteCore
 *
 * @phpstan-type ResourceRouteCore array{
 *   type: 'resource'|'apiResource',
 *   name: non-empty-string,
 *   controller: non-empty-string,                # FQCN
 *   only?: list<'index'|'create'|'store'|'show'|'edit'|'update'|'destroy'>,
 *   except?: list<'index'|'create'|'store'|'show'|'edit'|'update'|'destroy'>,
 *   parameters?: array<string,string>,
 *   names?: array<string,string>,
 *   shallow?: bool
 * }
 * @phpstan-type ResourceRoute array{ }&BaseRouteProps&ResourceRouteCore
 *
 * @phpstan-type RedirectRouteCore array{
 *   type: 'redirect',
 *   path: non-empty-string,
 *   to: non-empty-string,
 *   status?: int
 * }
 * @phpstan-type RedirectRoute array{ }&BaseRouteProps&RedirectRouteCore
 *
 * @phpstan-type ViewRouteCore array{
 *   type: 'view',
 *   path: non-empty-string,
 *   view: non-empty-string,
 *   data?: array<string,mixed>
 * }
 * @phpstan-type ViewRoute array{ }&BaseRouteProps&ViewRouteCore
 *
 * @phpstan-type FallbackRouteCore array{
 *   type: 'fallback',
 *   action: ControllerRef|non-empty-string
 * }
 * @phpstan-type FallbackRoute array{ }&BaseRouteProps&FallbackRouteCore
 *
 * @phpstan-type GroupRouteCore array{
 *   type: 'group',
 *   group?: GroupOptions,
 *   routes: non-empty-list<RouteNode>
 * }
 * @phpstan-type GroupRoute array{ }&BaseRouteProps&GroupRouteCore
 *
 * @phpstan-type RouteNode HttpRoute|ResourceRoute|RedirectRoute|ViewRoute|FallbackRoute|GroupRoute
 *
 * @phpstan-type RoutesFile array{
 *   group?: GroupOptions,
 *   routes: non-empty-list<RouteNode>
 * }
 */
interface RouteRegistry
{
    /**
     * Guarantee that a route id is globally unique.
     *
     * Implementations may reserve the id at validation time to prevent races.
     *
     * @param string $routeId
     * @return bool True if unique (or successfully reserved), false otherwise.
     */
    public function ensureUniqueGlobalId(string $routeId): bool;

    /**
     * Queue compiled route entries for a plugin (not yet active).
     *
     * @param non-empty-string $slug
     * @param non-empty-list<RouteNode> $routes
     */
    public function queueRoutes(string $slug, array $routes): void;
}