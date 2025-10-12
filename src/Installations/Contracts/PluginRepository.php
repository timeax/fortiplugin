<?php

namespace Timeax\FortiPlugin\Installations\Contracts;

interface PluginRepository
{
    /** @return array{id:int}|null */
    public function upsertPlugin(array $pluginData): ?array;

    /** @return array{id:int}|null */
    public function createVersion(int $pluginId, array $versionData): ?array;

    public function linkZip(int $pluginVersionId, int|string $zipId): void;

    public function saveMeta(int $pluginId, array $meta): void;
}
