<?php

namespace Timeax\FortiPlugin\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Timeax\FortiPlugin\Services\AuthService;
use Timeax\FortiPlugin\Support\FortiGates;

final class AuthController extends Controller
{
    public function __construct(private readonly AuthService $auth)
    {
    }

    public function login(Request $request): JsonResponse
    {
//        Gate::authorize(FortiGates::AUTHOR_LOGIN);
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:6'],
        ]);

        $out = $this->auth->login($data['email'], $data['password']);

        return response()->json([
            'ok' => true,
            'token' => $out['token'],
            'author' => $out['author'],
            'expires_at' => $out['expires_at'],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        Gate::authorize(FortiGates::AUTHOR_LOGOUT);

        $raw = $request->bearerToken();
        if ($raw) {
            $this->auth->logout($raw);
        }
        return response()->json(['ok' => true]);
    }
}