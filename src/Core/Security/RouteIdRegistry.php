<?php

namespace Timeax\FortiPlugin\Core\Security;

use Timeax\FortiPlugin\Exceptions\DuplicateRouteIdException;

/**
 * Tracks route IDs to enforce uniqueness within a plugin.
 */
final class RouteIdRegistry
{
    /**
     * @var array<string, array{file:string, path:string}>
     */
    private array $seen = [];

    /**
     * @throws DuplicateRouteIdException
     */
    public function register(string $id, string $file, string $jsonPath = ''): void
    {
        $id = trim($id);
        if ($id === '') {
            return; // schema should already require non-empty; be lenient here
        }

        if (isset($this->seen[$id])) {
            $first = $this->seen[$id];
            throw new DuplicateRouteIdException(
                $id,
                $first['file'],
                $first['path'],
                $file,
                $jsonPath
            );
        }

        $this->seen[$id] = ['file' => $file, 'path' => $jsonPath];
    }
}