<?php

namespace Timeax\FortiPlugin\Services;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Timeax\FortiPlugin\Models\Author;
use Timeax\FortiPlugin\Models\AuthorToken;

final class AuthService
{
    /** Login by email+password, returns raw token string */
    public function login(string $email, string $password, ?int $ttlMinutes = 60*24): array
    {
        $author = Author::query()->whereNotNull('email')->where('email', $email)->firstOrFail();

        if (!Hash::check($password, $author->password)) {
            abort(401, 'Invalid credentials');
        }

        $raw     = $this->newRawToken();
        $hash    = hash('sha256', $raw);
        $expires = now()->addMinutes($ttlMinutes ?? 1440);

        AuthorToken::create([
            'author_id'  => $author->id,
            'token_hash' => $hash,
            'expires_at' => $expires,
            'meta'       => ['scopes' => [
                // sensible defaults; tighten to your liking
                'forti-author-logout',
                'forti-packager-fetch-policy',
                'forti-placeholder-create',
                'forti-token-issue-placeholder',
                'forti-packager-register-fingerprint',
            ]],
        ]);

        return [
            'token'  => $raw,
            'author' => [
                'id'     => $author->id,
                'slug'   => $author->slug,
                'name'   => $author->name,
                'email'  => $author->email,
                'status' => $author->status->value,
            ],
            'expires_at' => $expires->toIso8601String(),
        ];
    }

    /** Revoke current author token by raw value (from Authorization header) */
    public function logout(string $raw): void
    {
        $hash = hash('sha256', $raw);
        $tok  = AuthorToken::query()->where('token_hash', $hash)->first();
        if ($tok) {
            $tok->revoked = true;
            $tok->save();
        }
    }

    /** Helper to create a strong raw token */
    public function newRawToken(): string
    {
        return 'forti_' . Str::random(64);
    }
}