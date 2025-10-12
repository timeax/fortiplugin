<?php

namespace Timeax\FortiPlugin\Installations\Support;

use Timeax\FortiPlugin\Installations\Contracts\Filesystem;

class InstallationLogStore
{
    public function __construct(
        private readonly Filesystem $fs
    ) {}

    private function pathFor(string $installRoot): string
    {
        return rtrim($installRoot, "\\/ ") . DIRECTORY_SEPARATOR . '.internal' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'installation.json';
    }

    private function readCurrent(string $path): array
    {
        if (is_file($path)) {
            return $this->fs->readJson($path);
        }
        return [
            'meta' => [],
            'logs' => [
                'validation_emits' => [],
                'installer_emits' => [],
            ],
        ];
    }

    /**
     * Expose current raw installation log state (read-only usage by sections).
     */
    public function getCurrent(string $installRoot): array
    {
        $path = $this->pathFor($installRoot);
        return $this->readCurrent($path);
    }

    /**
     * Minimal Phase 0: allow creating the installation.json shell (meta only) atomically.
     */
    public function writeMeta(string $installRoot, array $meta): void
    {
        $path = $this->pathFor($installRoot);
        $dir = dirname($path);
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0777, true) && !is_dir($dir)) {
                throw new \RuntimeException('Failed to create log directory: ' . $dir);
            }
        }
        $current = $this->readCurrent($path);
        $current['meta'] = $meta;
        $this->fs->writeAtomic($path, json_encode($current, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public function appendValidationEmit(string $installRoot, array $payload): void
    {
        $path = $this->pathFor($installRoot);
        $current = $this->readCurrent($path);
        $current['logs']['validation_emits'][] = $payload; // verbatim
        $this->fs->writeAtomic($path, json_encode($current, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public function appendInstallerEmit(string $installRoot, array $payload): void
    {
        $path = $this->pathFor($installRoot);
        $current = $this->readCurrent($path);
        $current['logs']['installer_emits'][] = $payload;
        $this->fs->writeAtomic($path, json_encode($current, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public function setVerification(string $installRoot, array $verification): void
    {
        $path = $this->pathFor($installRoot);
        $current = $this->readCurrent($path);
        $current['verification'] = $verification;
        $this->fs->writeAtomic($path, json_encode($current, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public function setZipValidation(string $installRoot, array $zipValidation): void
    {
        $path = $this->pathFor($installRoot);
        $current = $this->readCurrent($path);
        $current['zip_validation'] = $zipValidation;
        $this->fs->writeAtomic($path, json_encode($current, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public function setFileScan(string $installRoot, array $fileScan): void
    {
        $path = $this->pathFor($installRoot);
        $current = $this->readCurrent($path);
        $current['file_scan'] = $fileScan;
        $this->fs->writeAtomic($path, json_encode($current, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public function setDecision(string $installRoot, array $decision): void
    {
        $path = $this->pathFor($installRoot);
        $current = $this->readCurrent($path);
        $current['decision'] = $decision;
        $this->fs->writeAtomic($path, json_encode($current, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
