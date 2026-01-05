<?php

declare(strict_types=1);

namespace Timeax\FortiPlugin\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use RuntimeException;
use Timeax\FortiPlugin\Jobs\ActivatePluginVersionJob;
use Timeax\FortiPlugin\Jobs\InstallPluginZipJob;
use Timeax\FortiPlugin\Models\PluginZip;
use Timeax\FortiPlugin\Support\FortiGates;

final class PluginInstallController
{
    /**
     * Queue: Extract ZIP → build InstallMeta → run Installer (inside job).
     *
     * Request:
     *  - installer_token?: string
     *  - run_id?: uuid (IMPORTANT: pass this when resuming an ASK flow with installer_token)
     */
    public function install(Request $request, PluginZip $zip): JsonResponse
    {
        Gate::authorize(FortiGates::PLUGIN_INSTALL);

        $data = $request->validate([
            'installer_token' => ['nullable', 'string', 'max:2048'],
            'run_id'          => ['nullable', 'uuid'],
        ]);

        if (!is_string($zip->path) || !is_file($zip->path)) {
            return response()->json(['ok' => false, 'error' => 'ZIP_NOT_FOUND'], 422);
        }

        $metaData  = is_array($zip->meta ?? null) ? $zip->meta : [];
        $manifest  = is_array($metaData['manifest'] ?? null) ? $metaData['manifest'] : [];
        $pluginMan = is_array($manifest['plugin'] ?? null) ? $manifest['plugin'] : [];

        $placeholderId = (int) $zip->placeholder_id;

        $placeholderName = $this->sanitizePlaceholderName(
            (string) (
                $pluginMan['slug']
                ?? $manifest['slug']
                ?? $manifest['name']
                ?? ('placeholder-' . $placeholderId)
            )
        );

        $versionTag = (string) (
            $pluginMan['version']
            ?? $manifest['version']
            ?? '0.0.0'
        );

        $validatorConfig = is_array($metaData['validator_config'] ?? null)
            ? $metaData['validator_config']
            : [];

        $runId = (string) ($data['run_id'] ?? Str::uuid());
        $actor = (string) (auth()->user()?->email ?? 'system');

        InstallPluginZipJob::dispatch(
            zipId: $zip->id,
            zipPath:  $zip->path,
            placeholderId: $placeholderId,
            zipMeta: $metaData,
            placeholderName: $placeholderName,
            versionTag: $versionTag,
            validatorConfig: $validatorConfig,
            runId: $runId,
            actor: $actor,
            installerToken: $data['installer_token'] ?? null,
        );

        return response()->json([
            'ok'               => true,
            'queued'           => true,
            'run_id'           => $runId,
            'zip_id'           => (int) $zip->id,
            'placeholder_name' => $placeholderName,
            'version_tag'      => $versionTag,
        ], 202);
    }

    /**
     * Queue: activation step (inside job).
     *
     * Request:
     *  - plugin_version_id: int
     *  - run_id: uuid (must be the install run_id)
     */
    public function activate(Request $request, PluginZip $zip): JsonResponse
    {
        Gate::authorize(FortiGates::PLUGIN_ENABLE);

        $data = $request->validate([
            'plugin_version_id' => ['required', 'integer'],
            'run_id'            => ['required', 'uuid'],
        ]);

        $actor = (string) (auth()->user()?->email ?? 'system');

        ActivatePluginVersionJob::dispatch(
            pluginVersionId: (int) $data['plugin_version_id'],
            zipPlaceholderId: (int) $zip->placeholder_id,
            runId: (string) $data['run_id'],
            actor: $actor,
        );

        return response()->json([
            'ok'                => true,
            'queued'            => true,
            'run_id'            => (string) $data['run_id'],
            'plugin_version_id' => (int) $data['plugin_version_id'],
        ], 202);
    }

    private function sanitizePlaceholderName(string $name): string
    {
        if (!preg_match('/^[a-z0-9][a-z0-9._-]{0,100}$/i', $name)) {
            throw new RuntimeException('INVALID_PLACEHOLDER_NAME');
        }
        return $name;
    }
}
