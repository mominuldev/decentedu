<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Support\ApiResponse;
use App\Support\AuthPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /** Sanctum SPA cookie login. */
    public function login(LoginRequest $request): JsonResponse
    {
        $key = 'login:'.strtolower($request->input('email')).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'email' => ['Too many attempts. Try again in '.RateLimiter::availableIn($key).'s.'],
            ]);
        }

        if (! Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        $user = $request->user();
        if (! $user->status) {
            Auth::guard('web')->logout();
            throw ValidationException::withMessages(['email' => ['This account is disabled.']]);
        }

        // Default the active branch to the user's pinned/first branch.
        $default = $user->defaultBranch();
        if ($default) {
            $request->session()->put('active_branch_id', $default->id);
        }

        return ApiResponse::success(AuthPayload::for($user, $request), 'Signed in successfully.');
    }

    /** Hydrate the SPA on load. */
    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success(AuthPayload::for($request->user(), $request), 'OK');
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return ApiResponse::success(null, 'Signed out.');
    }
}
