<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            Log::warning('API login failed', [
                'email' => strtolower((string) $request->email),
                'ip' => $request->ip(),
            ]);
            throw ValidationException::withMessages([
                'email' => 'Identifiants incorrects.',
            ]);
        }

        if ($user->status !== 'active') {
            throw ValidationException::withMessages([
                'email' => 'Compte désactivé. Contactez le support.',
            ]);
        }

        $user->update(['last_login_at' => now()]);

        $deviceName = $request->device_name ?? 'api';
        $user->tokens()->where('name', $deviceName)->delete();
        $token = $user->createToken($deviceName)->plainTextToken;
        Log::info('API login success', [
            'user_id' => $user->id,
            'device_name' => $deviceName,
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user'  => $this->userPayload($user),
        ]);
    }

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'first_name'   => ['required', 'string', 'max:100'],
            'last_name'    => ['required', 'string', 'max:100'],
            'email'        => ['required', 'email', 'unique:users,email'],
            'phone'        => ['required', 'string', 'max:30'],
            'password'     => ['required', 'min:8'],
            'company_name' => ['required', 'string', 'max:255'],
            'vat_number'   => ['nullable', 'string'],
            'country'      => ['required', 'string', 'size:2'],
        ]);

        $org = Organization::create([
            'name'       => $data['company_name'],
            'vat_number' => $data['vat_number'] ?? null,
            'country'    => $data['country'],
            'status'     => 'active',
            'user_tier'  => 'trial',
        ]);

        $user = User::create([
            'name'            => $data['first_name'] . ' ' . $data['last_name'],
            'first_name'      => $data['first_name'],
            'last_name'       => $data['last_name'],
            'email'           => $data['email'],
            'phone'           => $data['phone'],
            'password'        => Hash::make($data['password']),
            'organization_id' => $org->id,
            'role'            => 'client',
            'status'          => 'active',
        ]);

        $token = $user->createToken('api')->plainTextToken;
        Log::info('API register success', [
            'user_id' => $user->id,
            'organization_id' => $org->id,
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user'  => $this->userPayload($user),
        ], 201);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($this->userPayload($request->user()));
    }

    public function logout(Request $request): JsonResponse
    {
        Log::info('API logout', [
            'user_id' => $request->user()?->id,
            'ip' => $request->ip(),
        ]);

        $token = $request->user()->currentAccessToken();

        if ($token) {
            $token->delete();
        } else {
            $request->user()->tokens()->delete();
        }

        return response()->json(['message' => 'Déconnecté.']);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);
        \Illuminate\Support\Facades\Password::sendResetLink($request->only('email'));
        return response()->json(['message' => 'Email envoyé si le compte existe.']);
    }

    private function userPayload(User $user): array
    {
        $user->load('organization');
        return [
            'id'           => $user->id,
            'name'         => $user->name,
            'first_name'   => $user->first_name,
            'last_name'    => $user->last_name,
            'email'        => $user->email,
            'role'         => $user->role,
            'status'       => $user->status,
            'organization' => $user->organization ? [
                'id'        => $user->organization->id,
                'name'      => $user->organization->name,
                'tier'      => $user->organization->user_tier,
                'country'   => $user->organization->country,
                'deposit'   => $user->organization->deposit_balance,
            ] : null,
        ];
    }
}
