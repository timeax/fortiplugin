<?php

namespace Timeax\FortiPlugin\Core\Install;

final class PhpEmitter
{
    private array $lines = [];
    private int $indent = 0;

    public function line(string $code = ''): void
    {
        $this->lines[] = str_repeat('    ', $this->indent) . $code;
    }

    public function open(string $code): void
    {
        $this->line($code);
        $this->indent++;
    }

    public function close(string $code = ''): void
    {
        $this->indent = max(0, $this->indent - 1);
        if ($code !== '') $this->line($code);
    }

    public function code(): string
    {
        return implode("\n", $this->lines) . "\n";
    }
}