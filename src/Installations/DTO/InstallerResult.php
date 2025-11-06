<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\DTO;

/**
 * Canonical result wrapper for the full Installer pipeline.
 *
 * Status values:
 *  - 'ok'    → installation completed successfully
 *  - 'ask'   → installation paused (e.g., background scans / host decision needed)
 *  - 'break' → hard stop by policy (do not continue)
 *  - 'fail'  → an error occurred during installation
 *
 * Provides convenient inspectors (isAsking, passed, failed, isBreak) and getters.
 *
 * @phpstan-type TInstallerStatus 'ok'|'ask'|'break'|'fail'
 * @phpstan-type TInstallerResult array{
 *   status: TInstallerStatus,
 *   summary?: array|null,
 *   meta?: array<string,mixed>|null,
 *   plugin_id?: int|null,
 *   plugin_version_id?: int|null,
 *   extra?: array<string,mixed>|null
 * }
 */
final readonly class InstallerResult implements ArraySerializable
{
    /**
     * @param TInstallerStatus $status
     * @param InstallSummary|null $summary
     * @param array<string,mixed>|null $meta
     * @param int|null $plugin_id
     * @param int|null $plugin_version_id
     * @param array<string,mixed>|null $extra Arbitrary additional fields the host wants to carry
     */
    public function __construct(
        public string        $status,
        public ?InstallSummary $summary = null,
        public ?array        $meta = null,
        public ?int          $plugin_id = null,
        public ?int          $plugin_version_id = null,
        public ?array        $extra = null,
    ) {}

    /** @param TInstallerResult $data */
    public static function fromArray(array $data): static
    {
        $summary = null;
        if (array_key_exists('summary', $data) && $data['summary'] !== null) {
            $summary = $data['summary'] instanceof InstallSummary
                ? $data['summary']
                : InstallSummary::fromArray((array)$data['summary']);
        }

        return new self(
            status: (string)$data['status'],
            summary: $summary,
            meta: isset($data['meta']) ? (array)$data['meta'] : null,
            plugin_id: isset($data['plugin_id']) ? (int)$data['plugin_id'] : null,
            plugin_version_id: isset($data['plugin_version_id']) ? (int)$data['plugin_version_id'] : null,
            extra: isset($data['extra']) ? (array)$data['extra'] : null,
        );
    }

    /** @return TInstallerResult */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'summary' => $this->summary?->toArray(),
            'meta' => $this->meta,
            'plugin_id' => $this->plugin_id,
            'plugin_version_id' => $this->plugin_version_id,
            'extra' => $this->extra,
        ];
    }

    // ── Inspectors ─────────────────────────────────────────────────────────

    public function isAsking(): bool
    {
        return $this->status === 'ask';
    }

    public function passed(): bool
    {
        return $this->status === 'ok';
    }

    public function failed(): bool
    {
        return $this->status === 'fail';
    }

    public function isBreak(): bool
    {
        return $this->status === 'break';
    }

    // ── Accessors ──────────────────────────────────────────────────────────

    public function getSummary(): ?InstallSummary
    {
        return $this->summary;
    }

    /** @return array<string,mixed>|null */
    public function getMeta(): ?array
    {
        return $this->meta;
    }

    public function getPluginId(): ?int
    {
        return $this->plugin_id;
    }

    public function getPluginVersionId(): ?int
    {
        return $this->plugin_version_id;
    }

    /**
     * Generic accessor over all stored data (summary/meta/ids/extra), useful for UIs.
     * If $key is null, returns the whole flattened payload.
     *
     * @param string|null $key
     * @param mixed|null $default
     * @return mixed
     */
    public function getData(?string $key = null, mixed $default = null): mixed
    {
        $all = [
                'status' => $this->status,
                'summary' => $this->summary?->toArray(),
                'meta' => $this->meta,
                'plugin_id' => $this->plugin_id,
                'plugin_version_id' => $this->plugin_version_id,
            ] + ($this->extra ?? []);

        if ($key === null) {
            return $all;
        }
        return $all[$key] ?? $default;
    }

    // ── Factories (optional sugar) ─────────────────────────────────────────

    /** @param array<string,mixed>|null $meta */
    public static function ok(?InstallSummary $summary = null, ?array $meta = null, ?int $pluginId = null, ?int $pluginVersionId = null, ?array $extra = null): self
    {
        return new self('ok', $summary, $meta, $pluginId, $pluginVersionId, $extra);
    }

    /** @param array<string,mixed>|null $meta */
    public static function ask(?InstallSummary $summary = null, ?array $meta = null, ?array $extra = null): self
    {
        return new self('ask', $summary, $meta, null, null, $extra);
    }

    /** @param array<string,mixed>|null $meta */
    public static function break(?InstallSummary $summary = null, ?array $meta = null, ?array $extra = null): self
    {
        return new self('break', $summary, $meta, null, null, $extra);
    }

    /** @param array<string,mixed>|null $meta */
    public static function fail(?InstallSummary $summary = null, ?array $meta = null, ?array $extra = null): self
    {
        return new self('fail', $summary, $meta, null, null, $extra);
    }
}