<?php

namespace App\Http\Controllers\Portal;

use App\Domain\Suppliers\Actions\AuthenticateSupplier;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function create()
    {
        return view('portal.login');
    }

    public function store(LoginRequest $request, AuthenticateSupplier $action)
    {
        $user = $action->execute($request->validated());
        if ($request->is('api/*')) {
            return response()->json(['access_token' => $user->createToken('supplier-api', ['supplier'], now()->addHours(8))->plainTextToken, 'token_type' => 'Bearer', 'expires_at' => now()->addHours(8)->toIso8601String()])->header('Cache-Control', 'no-store');
        }
        Auth::guard('supplier')->login($user);
        $request->session()->regenerate();

        return redirect()->route('portal.home');
    }

    public function destroy(Request $request)
    {
        if ($request->is('api/*')) {
            $request->user()->currentAccessToken()?->delete();

            return response()->noContent();
        }
        Auth::guard('supplier')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('portal.login');
    }
}
