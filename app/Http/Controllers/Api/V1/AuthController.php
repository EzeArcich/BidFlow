<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Organizations\Actions\IssueUserToken;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Resources\Api\V1\UserResource;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function login(LoginRequest $request, IssueUserToken $action)
    {
        $result = $action->execute($request->validated());

        return response()->json([
            'access_token' => $result['token'], 'token_type' => 'Bearer',
            'expires_at' => $result['expires_at']->toIso8601String(),
            'data' => new UserResource($result['user']->load('organizations')),
        ])->header('Cache-Control', 'no-store');
    }

    public function me(Request $request)
    {
        return (new UserResource($request->user()->load('organizations')))->response()->header('Cache-Control', 'no-store');
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->noContent();
    }
}
