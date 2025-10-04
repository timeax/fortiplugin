<?php

namespace Timeax\FortiPlugin\Support;

class PluginSignature
{
    /**
     * The full report array returned by the verifier.
     * @var array
     */
    protected array $report;

    /**
     * The decrypted signature data (decoded JSON).
     * @var array
     */
    protected array $signature;

    /**
     * PluginSignature constructor.
     *
     * @param array $report The array returned by PluginSignatureVerifier::verify, must include 'signature_data'.
     */
    public function __construct(array $report)
    {
        $this->report = $report;
        $this->signature = $report['signature_data'] ?? [];
    }

    /**
     * Get the full verification report.
     */
    public function getReport(): array
    {
        return $this->report;
    }

    /**
     * Get the full signature (decrypted).
     */
    public function getSignature(): array
    {
        return $this->signature;
    }

    /**
     * Get plugin files list.
     */
    public function getFiles(): array
    {
        return $this->signature['files'] ?? [];
    }

    /**
     * Get the plugin author array.
     */
    public function getAuthor(): ?array
    {
        return $this->signature['author'] ?? null;
    }

    /**
     * Get the validation array.
     */
    public function getValidation(): ?array
    {
        return $this->signature['validation'] ?? null;
    }

    /**
     * Get the plugin array (meta).
     */
    public function getPlugin(): ?array
    {
        return $this->signature['plugin'] ?? null;
    }

    /**
     * Get the slug.
     */
    public function getSlug(): ?string
    {
        return $this->signature['plugin']['slug'] ?? null;
    }

    /**
     * Get the plugin version.
     */
    public function getVersion(): ?string
    {
        return $this->signature['plugin']['version'] ?? null;
    }

    /**
     * Get the policy array.
     */
    public function getPolicy(): ?array
    {
        return $this->signature['policy'] ?? null;
    }

    /**
     * Get the host array.
     */
    public function getHost(): ?array
    {
        return $this->signature['host'] ?? null;
    }

    /**
     * Get the timestamp.
     */
    public function getTimestamp(): ?string
    {
        return $this->signature['timestamp'] ?? null;
    }

    /**
     * Get a value from the signature via dot path (e.g., 'author.email').
     *
     * @param string $path
     * @param mixed|null $default
     * @return mixed
     */
    public function get(string $path, mixed $default = null): mixed
    {
        $parts = explode('.', $path);
        $value = $this->signature;
        foreach ($parts as $part) {
            if (is_array($value) && array_key_exists($part, $value)) {
                $value = $value[$part];
            } else {
                return $default;
            }
        }
        return $value;
    }

    /**
     * Get the author email.
     */
    public function getAuthorEmail(): ?string
    {
        return $this->get('author.email');
    }

    /**
     * Get the plugin name (if present).
     */
    public function getPluginName(): ?string
    {
        return $this->get('plugin.name');
    }

    // Add other meta accessors as needed...
}