<?php

namespace Timeax\FortiPlugin\Installations\Contracts;

interface RouteRegistry
{
    public function ensureUniqueGlobalId(string $routeId): bool;

    public function queueRoutes(string $slug, array $routes): void;
}
