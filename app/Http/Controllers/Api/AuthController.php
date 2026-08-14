<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'login' => ['nullable', 'string'],
            'email' => ['nullable', 'string'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string'],
        ]);

        $login = trim($data['login'] ?? $data['email'] ?? '');

        if ($login === '') {
            throw ValidationException::withMessages([
                'login' => ['El campo usuario o correo es obligatorio.'],
            ]);
        }

        $user = $this->resolveUser($login);

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'login' => ['Credenciales inválidas.'],
            ]);
        }

        if ($user->status !== 'active') {
            return response()->json([
                'message' => 'Cuenta suspendida.',
            ], 403);
        }

        $token = $user->createToken($data['device_name'] ?? 'api')->plainTextToken;
        $user->update(['last_login_at' => now()]);

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'username' => $user->username,
                'display_name' => $user->display_name,
                'role' => $user->role,
                'status' => $user->status,
                'owner_id' => $user->owner_id,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Sesión cerrada.']);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        return response()->json([
            'id' => $user->id,
            'email' => $user->email,
            'username' => $user->username,
            'display_name' => $user->display_name,
            'role' => $user->role,
            'status' => $user->status,
            'owner_id' => $user->owner_id,
            'effective_owner_id' => $user->effectiveOwnerId(),
        ]);
    }

    protected function resolveUser(string $login): ?User
    {
        if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
            return User::whereRaw('LOWER(email) = ?', [Str::lower($login)])->first();
        }

        return User::whereRaw('LOWER(username) = ?', [Str::lower($login)])->first();
    }
}