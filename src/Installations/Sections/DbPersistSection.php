<?php

namespace Timeax\FortiPlugin\Installations\Sections;

use DateTimeImmutable;
use Throwable;
use Timeax\FortiPlugin\Installations\Contracts\PluginRepository;
use Timeax\FortiPlugin\Installations\Support\InstallationLogStore;

class DbPersistSection
{
    /**
     * Phase 7: Persist the installed plugin state into DB using PluginRepository.
     * - Upsert Plugin, create PluginVersion, link PluginZip (assumed verified by gate).
     * - Mirror packages meta from installation.json.packages into Plugin.meta.packages.
     * - Update installation.json with db_persist block (ids, timestamps) and emit installer event.
     *
     * Returns: ['status' => 'ok'|'failed', 'plugin_id'?, 'plugin_version_id'?, 'error'?, 'saved_at']
     */
    public function run(
        PluginRepository $repo,
        InstallationLogStore $logStore,
        string $installRoot,
        int|string $zipId,
        ?callable $emit = null
    ): array {
        $state = $logStore->getCurrent($installRoot);
        $now = (new DateTimeImmutable('now'))->format(DATE_ATOM);

        // Derive minimal data
        $slug = (string)($state['meta']['slug'] ?? ($state['meta']['name'] ?? 'plugin'));
        $packages = (array)($state['packages'] ?? []);
        $installPaths = (array)($state['install']['paths'] ?? []);
        $versionId = (string)($state['install']['version_id'] ?? '');

        $pluginId = null;
        $versionRecId = null;
        $error = null;

        try {
            $pluginRec = $repo->upsertPlugin([
                'name' => $slug,
                'slug' => $slug,
                'paths' => $installPaths,
            ]);
            if (!$pluginRec || !isset($pluginRec['id'])) {
                throw new \RuntimeException('DB_PERSIST_FAILED: upsertPlugin returned null');
            }
            $pluginId = (int)$pluginRec['id'];

            $versionRec = $repo->createVersion($pluginId, [
                'version_id' => $versionId,
                'paths' => $installPaths,
            ]);
            if (!$versionRec || !isset($versionRec['id'])) {
                throw new \RuntimeException('DB_PERSIST_FAILED: createVersion returned null');
            }
            $versionRecId = (int)$versionRec['id'];

            $repo->linkZip($versionRecId, $zipId);

            // Mirror packages into plugin meta
            $repo->saveMeta($pluginId, ['packages' => $packages]);

            $block = [
                'status' => 'ok',
                'plugin_id' => $pluginId,
                'plugin_version_id' => $versionRecId,
                'linked_zip_id' => (string)$zipId,
                'saved_at' => $now,
            ];
            $logStore->setDbPersist($installRoot, $block);

            if ($emit) {
                try {
                    $emit([
                        'title' => 'Installer: DB Persist',
                        'description' => 'DB rows created and linked',
                        'error' => null,
                        'stats' => ['filePath' => null, 'size' => null],
                        'meta' => $block,
                    ]);
                } catch (Throwable $_) {}
            }

            // Also append to installer emits
            try {
                $logStore->appendInstallerEmit($installRoot, [
                    'title' => 'Installer: DB Persist',
                    'description' => 'DB rows created and linked',
                    'error' => null,
                    'stats' => ['filePath' => null, 'size' => null],
                    'meta' => $block,
                ]);
            } catch (Throwable $_) {}

            return $block;
        } catch (Throwable $e) {
            $error = $e->getMessage();
            $block = [
                'status' => 'failed',
                'error' => $error,
                'saved_at' => $now,
            ];
            $logStore->setDbPersist($installRoot, $block);
            if ($emit) {
                try {
                    $emit([
                        'title' => 'Installer: DB Persist',
                        'description' => 'Persist failed',
                        'error' => ['detail' => $error, 'code' => 'DB_PERSIST_FAILED'],
                        'stats' => ['filePath' => null, 'size' => null],
                        'meta' => $block,
                    ]);
                } catch (Throwable $_) {}
            }
            try {
                $logStore->appendInstallerEmit($installRoot, [
                    'title' => 'Installer: DB Persist',
                    'description' => 'Persist failed',
                    'error' => ['detail' => $error, 'code' => 'DB_PERSIST_FAILED'],
                    'stats' => ['filePath' => null, 'size' => null],
                    'meta' => $block,
                ]);
            } catch (Throwable $_) {}
            return $block;
        }
    }
}
