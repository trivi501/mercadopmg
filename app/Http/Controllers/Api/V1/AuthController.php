<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    /** Vigencia del token de la app móvil. */
    public const int TOKEN_TTL_DAYS = 30;

    public function __construct(private readonly AuditLogger $audit) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $login = mb_strtolower(trim((string) $request->input('username')));

        /** @var User|null $user */
        $user = User::query()->where('username', $login)->orWhere('email', $login)->first();

        if (! $user || ! Hash::check((string) $request->input('password'), $user->password)) {
            throw ValidationException::withMessages(['username' => 'Usuario o contraseña incorrectos.']);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages(['username' => 'Su usuario está desactivado.']);
        }

        $token = $user->createToken(
            name: (string) $request->input('device_name'),
            expiresAt: now()->addDays(self::TOKEN_TTL_DAYS),
        );

        $user->forceFill(['last_login_at' => now()])->saveQuietly();
        auth()->setUser($user);
        $this->audit->log(AuditAction::Login, 'Autenticación', $user, null, ['dispositivo' => $request->input('device_name')], 'Inicio de sesión desde la app');

        return response()->json([
            'data' => [
                'token' => $token->plainTextToken,
                'token_type' => 'Bearer',
                'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
                'user' => new UserResource($user->load('roles')),
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        $this->audit->log(AuditAction::Logout, 'Autenticación', $request->user(), null, null, 'Cierre de sesión desde la app');

        return response()->json(['message' => 'Sesión cerrada.']);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user()?->load('roles'));
    }
}
