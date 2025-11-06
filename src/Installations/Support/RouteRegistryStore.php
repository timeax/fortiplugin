<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Support;

use JsonException;

final readonly class RouteRegistryStore
{
    public function __construct(private AtomicFilesystem $afs) {}

    public function path(string $pluginRoot): string
    {
        return rtrim($pluginRoot, "\\/") . DIRECTORY_SEPARATOR . '.internal' . DIRECTORY_SEPARATOR . 'routes.registry.json';
    }

    /** @return list<array{route:string|array, id:string, content:string, file:string}> */
    public function read(string $pluginRoot): array
    {
        $p = $this->path($pluginRoot);
        if (!$this->afs->fs()->exists($p)) return [];
        $doc = $this->afs->fs()->readJson($p);
        return is_array($doc) ? array_values($doc) : [];
    }

    /** @param list<array{route:string|array, id:string, content:string, file:string}> $entries
     * @throws JsonException
     */
    public function write(string $pluginRoot, array $entries): void
    {
        $p = $this->path($pluginRoot);
        $this->afs->ensureParentDirectory($p);
        $this->afs->writeJsonAtomic($p, array_values($entries), true);
    }
}