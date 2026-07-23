<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\AuthPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password as PasswordRule;
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
            // Exponential backoff: 30s, 60s, 120s, 240s, 480s, capped at 15 minutes.
            $decay = min(30 * 2 ** RateLimiter::attempts($key), 900);
            RateLimiter::hit($key, $decay);
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

    /** Self-service password change (also clears a pending forced-reset flag). */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $request->user()->update([
            'password' => Hash::make($request->validated('password')),
            'must_reset_password' => false,
        ]);

        return ApiResponse::success(null, 'Password updated.');
    }

    /** Uniform response regardless of whether the email exists — avoids account enumeration. */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        Password::sendResetLink($request->only('email'));

        return ApiResponse::success(null, 'If that email is registered, a reset link has been sent.');
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::reset($data, function (User $user, string $password) {
            $user->update(['password' => Hash::make($password), 'must_reset_password' => false]);
        });

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => [__($status)]]);
        }

        return ApiResponse::success(null, 'Password reset. You can now sign in.');
    }

    /** Active sessions for the current user (SESSION_DRIVER=database backs this). */
    public function sessions(Request $request): JsonResponse
    {
        $rows = DB::table('sessions')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('last_activity')
            ->get(['id', 'ip_address', 'user_agent', 'last_activity']);

        return ApiResponse::success($rows->map(fn ($s) => [
            'id' => $s->id,
            'ip_address' => $s->ip_address,
            'user_agent' => $s->user_agent,
            'last_active' => date('Y-m-d H:i', $s->last_activity),
            'is_current' => $s->id === $request->session()->getId(),
        ])->values(), 'Sessions retrieved.');
    }

    public function revokeSession(Request $request, string $id): JsonResponse
    {
        if ($id === $request->session()->getId()) {
            return ApiResponse::error('Use logout to end the current session.', 'CANNOT_REVOKE_CURRENT', 422);
        }

        DB::table('sessions')->where('id', $id)->where('user_id', $request->user()->id)->delete();

        return ApiResponse::success(null, 'Session revoked.');
    }
}
