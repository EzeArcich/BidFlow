<?php

namespace App\Domain\Organizations\Actions;

use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Hash;

class IssueUserToken
{
    public function execute(array $credentials): array
    {
        $user = User::where('email', $credentials['email'])->first();
        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw new AuthenticationException('Invalid credentials.');
        }
        $expires = now()->addMinutes(config('bidding.api_token_ttl_minutes'));
        $token = $user->createToken($credentials['device_name'], ['rfq:read', 'rfq:write'], $expires);

        return ['user' => $user, 'token' => $token->plainTextToken, 'expires_at' => $expires];
    }
}
