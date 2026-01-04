<?php

declare(strict_types=1);

namespace Timeax\FortiPlugin\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;
use Timeax\FortiPlugin\Services\PluginInstallationService;

final class InstallPluginZipJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 3600;

    /**
     * @param array<string,mixed> $zipMeta
     * @param array<string,mixed> $validatorConfig
     */
    public function __construct(
        public readonly int $zipId,
        public readonly string $zipPath,
        public readonly int $placeholderId,
        public readonly array $zipMeta,
        public readonly string $placeholderName,
        public readonly string $versionTag,
        public readonly array $validatorConfig,
        public readonly string $runId,
        public readonly string $actor,
        public readonly ?string $installerToken = null,
    ) {}


    /**
     * @throws Throwable
     */
    public function handle(
        PluginInstallationService $service,
    ): void {
        $result = $service->installFromZip($this->zipPath, [
            'zipId' => $this->zipId,
            'placeholderId' => $this->placeholderId,
            'placeholderName' => $this->placeholderName,
            'versionTag' => $this->versionTag,
            'validatorConfig' => $this->validatorConfig,
            'runId' => $this->runId,
            'actor' => $this->actor,
            'installerToken' => $this->installerToken,
        ]);

        cache()->put(
            "fortiplugin:install:{$this->runId}",
            $this->normalizeResult($result),
            now()->addDay()
        );
    }

    private function normalizeResult(mixed $result): array
    {
        if (is_array($result)) return $result;
        if (is_object($result) && method_exists($result, 'toArray')) {
            return (array) $result->toArray();
        }
        return is_object($result) ? get_object_vars($result) : ['value' => $result];
    }
}
