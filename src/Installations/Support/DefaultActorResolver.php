<?php

namespace Timeax\FortiPlugin\Installations\Support;

use Throwable;
use Timeax\FortiPlugin\Installations\Contracts\ActorResolver;
use function function_exists;

class DefaultActorResolver implements ActorResolver
{
    public function resolve(): string
    {
        // Try Laravel auth() if available, else fallback
        try {
            if (function_exists('auth')) {
                $user = auth()->user();
                if ($user && method_exists($user, 'getAuthIdentifier')) {
                    $id = $user->getAuthIdentifier();
                    if (is_scalar($id) && $id !== '') {
                        return (string)$id;
                    }
                }
            }
        } catch (Throwable $_) {}
        return 'system';
    }
}
