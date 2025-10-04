<?php

namespace Timeax\FortiPlugin\Support;

use Illuminate\Support\Facades\Gate;
use ReflectionClass;
use Throwable;

final class FortiGateRegistrar
{
    /**
     * Register all FortiGates::* abilities with Laravel Gate.
     *
     * @param callable|null $resolver fn(string $ability, mixed $actor, mixed $resource): bool
     */
    public static function register(?callable $resolver = null): void
    {
        // Default resolver: use config-driven decisions only.
        $resolver ??= static function (string $ability, $actor, $resource): bool {
            // 1) Dev override
            if (config('fortiplugin.allow_all_gates', false) === true) {
                return true;
            }

            // 2) Read-only allowances (configurable)
            if (config('fortiplugin.allow_read_ops', true) === true) {
                if (str_contains($ability, 'view') || str_contains($ability, 'list')) {
                    return true;
                }
            }

            // 3) Scope-based allow (from request header)
            $header = (string)config('fortiplugin.scope_header', 'X-Forti-Scopes');
            $scopesHeader = request()->headers->get($header);

            if ($scopesHeader) {
                $scopes = array_map('trim', explode(',', $scopesHeader));
                if (in_array($ability, $scopes, true)) {
                    return true;
                }
            }

            // 4) Default: deny
            return false;
        };

        $constants = (new ReflectionClass(FortiGates::class))->getConstants();

        foreach ($constants as $ability) {
            Gate::define($ability, static function ($actor = null, $resource = null) use ($ability, $resolver) {
                try {
                    return $resolver($ability, $actor, $resource);
                } catch (Throwable $e) {
                    report($e);
                    return false; // fail-closed
                }
            });
        }
    }
}