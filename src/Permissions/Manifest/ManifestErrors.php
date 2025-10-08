<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Manifest;

use InvalidArgumentException;

/**
 * Small error bag for manifest validation.
 * - Store path-scoped messages.
 * - Render as lines or a single string.
 * - Optionally parse the InvalidArgumentException your core validator throws.
 */
final class ManifestErrors
{
    /** @var array<int,array{path:string,message:string}> */
    private array $items = [];

    public function add(string $path, string $message): void
    {
        $this->items[] = ['path' => $path, 'message' => $message];
    }

    public function merge(self $other): void
    {
        array_push($this->items, ...$other->items);
    }

    /** @return array<int,array{path:string,message:string}> */
    public function all(): array
    {
        return $this->items;
    }

    /** @return string[] "path: message" */
    public function messages(): array
    {
        return array_map(
            static fn ($i) => "{$i['path']}: {$i['message']}",
            $this->items
        );
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function __toString(): string
    {
        return implode("\n- ", $this->messages());
    }

    /**
     * Attempt to parse the message produced by PermissionManifestValidator,
     * which typically formats as:
     *   "Permission manifest validation failed:\n- $.path: message\n- ..."
     */
    public static function fromException(InvalidArgumentException $e): self
    {
        $bag = new self();
        $msg = $e->getMessage();

        // Extract "- path: message" lines
        foreach (preg_split('/\r?\n/', $msg) ?: [] as $line) {
            $line = ltrim($line);
            if (str_starts_with($line, '- ')) {
                $rest = substr($line, 2);
                // Split into "path: message" if possible
                $parts = explode(':', $rest, 2);
                $path = trim($parts[0] ?? '$');
                $text = trim($parts[1] ?? $rest);
                $bag->add($path, $text);
            }
        }

        if ($bag->isEmpty()) {
            // Fallback single message when parsing fails
            $bag->add('$', $msg);
        }

        return $bag;
    }
}