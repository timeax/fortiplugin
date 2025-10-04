<?php

namespace Timeax\FortiPlugin\Lib\Network;

use Timeax\FortiPlugin\Core\ChecksModulePermission;

class CurlSession
{
    use ChecksModulePermission, CurlExecTrait;

    public function __construct(string $url)
    {
        $this->url = $url;
        $this->handle = curl_init($url);
    }

    public function setopt(int $option, mixed $value): static
    {
        curl_setopt($this->handle, $option, $value);
        return $this;
    }

    public function setoptArray(array $options): static
    {
        foreach ($options as $key => $value) {
            curl_setopt($this->handle, $key, $value);
        }
        return $this;
    }
    public function getInfo(): array
    {
        return curl_getinfo($this->handle);
    }

    public function getError(): string
    {
        return curl_error($this->handle);
    }

    public function close(): void
    {
        curl_close($this->handle);
    }

    public function __destruct()
    {
        $this->close();
    }
}