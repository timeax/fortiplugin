<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Contracts;

use Timeax\FortiPlugin\Models\Plugin;

interface RegistryWriter
{
    /**
     * Prepare any filesystem/registry changes for activation.
     * Must be idempotent and safe to call more than once.
     *
     * Return two closures for a 2-phase commit:
     *  - commit(): void   → publish staged changes
     *  - rollback(): void → revert staged work (best effort)
     *
     * @return array{
     *   commit: callable():void,
     *   rollback: callable():void,
     *   meta?: array<string,mixed>
     * }
     */
    public function stage(Plugin $plugin, int|string $versionId, string $installedPluginRoot): array;
}