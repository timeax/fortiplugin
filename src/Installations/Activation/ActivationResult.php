<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Activation;

final class ActivationResult
{
    /** @var 'ok'|'fail' */
    public string $status;
    /** @var array<string,mixed> */
    public array $data;

    private function __construct(string $status, array $data = [])
    {
        $this->status = $status;
        $this->data = $data;
    }

    /** @param array<string,mixed> $data */
    public static function ok(array $data = []): self
    {
        return new self('ok', $data);
    }

    /** @param array<string,mixed> $data */
    public static function fail(array $data = []): self
    {
        return new self('fail', $data);
    }

    public function isOk(): bool
    {
        return $this->status === 'ok';
    }

    public function isFail(): bool
    {
        return $this->status === 'fail';
    }

    /** @return array<string,mixed> */
    public function getData(): array
    {
        return $this->data;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }
}