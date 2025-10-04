<?php

namespace Timeax\FortiPlugin\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Timeax\FortiPlugin\Models\AuthorToken;
use Timeax\FortiPlugin\Models\PluginToken;

final class FortiTokenGuard
{
    public function handle(Request $request, Closure $next)
    {
        $auth = $request->bearerToken();
        if (!$auth) {
            return $next($request); // allow public endpoints; controllers/gates still decide
        }

        $hash = hash('sha256', $auth);

        // 1) Try author-level token (login session)
        $aTok = AuthorToken::query()
            ->where('token_hash', $hash)
            ->where('revoked', false)
            ->where('expires_at', '>=', now())
            ->first();

        if ($aTok) {
            $aTok->forceFill(['last_used' => now()])->saveQuietly();
            $request->attributes->set('forti.token_type', 'author');
            $request->attributes->set('forti.author_id', $aTok->author_id);

            // scopes header for Gate resolver
            $scopes = collect(($aTok->meta['scopes'] ?? []) ?: [])->filter()->all();
            if (!empty($scopes)) {
                $request->headers->set(config('fortiplugin.scope_header', 'X-Forti-Scopes'), implode(',', $scopes));
            }
            return $next($request);
        }

        // 2) Try placeholder-level token
        $pTok = PluginToken::query()
            ->where('token_hash', $hash)
            ->where('revoked', false)
            ->where('expires_at', '>=', now())
            ->first();

        if ($pTok) {
            $pTok->forceFill(['last_used' => now()])->saveQuietly();
            $request->attributes->set('forti.token_type', 'placeholder');
            $request->attributes->set('forti.placeholder_id', $pTok->plugin_placeholder_id);
            $request->attributes->set('forti.author_id', $pTok->author_id); // may be null

            $scopes = collect(($pTok->meta['scopes'] ?? []) ?: [])->filter()->all();
            if (!empty($scopes)) {
                $request->headers->set(config('fortiplugin.scope_header', 'X-Forti-Scopes'), implode(',', $scopes));
            }
        }

        return $next($request);
    }
}