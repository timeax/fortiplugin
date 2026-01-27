<?php

namespace Timeax\FortiPlugin\Support;

use Timeax\FortiPlugin\Models\Author;

final class FortiAuth
{
    /**
     * Get the current authenticated author.
     *
     * @return Author|null
     */
    public static function author(): ?Author
    {
        $authorId = request()->attributes->get('forti.author_id');

        if (!$authorId) {
            return null;
        }

        return Author::query()->find($authorId);
    }

    /**
     * Determine if an author is authenticated.
     *
     * @return bool
     */
    public static function check(): bool
    {
        return request()->attributes->has('forti.author_id');
    }

    /**
     * Get the ID of the current authenticated author.
     *
     * @return int|null
     */
    public static function id(): ?int
    {
        return request()->attributes->get('forti.author_id');
    }
}
