<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Evaluation\Dto;

use Timeax\FortiPlugin\Permissions\Contracts\PermissionRequestInterface;

final readonly class FileRequest implements PermissionRequestInterface
{
    public function __construct(
        public string $action,   // read|write|append|delete|mkdir|rmdir|list
        public string $baseDir,
        public string $path
    ) {}

    public static function fromArray(array $a): self
    {
        return new self(
            (string)$a['action'], (string)$a['baseDir'], (string)$a['path']
        );
    }

    public function toArray(): array
    {
        return [
            'action'  => $this->action,
            'baseDir' => $this->baseDir,
            'path'    => $this->path,
        ];
    }
}