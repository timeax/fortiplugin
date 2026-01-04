<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Ui\Embeds;

use RuntimeException;

final class EmbedResolveException extends RuntimeException
{
    public function __construct(
        public readonly int $status,
        string $message
    ) {
        parent::__construct($message);
    }

    public static function badRequest(string $message): self
    {
        return new self(422, $message);
    }

    public static function notFound(string $message): self
    {
        return new self(404, $message);
    }

    public static function internal(string $message): self
    {
        return new self(500, $message);
    }
}
