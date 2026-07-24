<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $org = Organization::create(['name' => 'Test Org', 'slug' => 'test-org']);
        $this->branch = Branch::create(['organization_id' => $org->id, 'name' => 'Main Branch', 'code' => 'MAIN']);

        // Sanctum only starts a session for requests it recognizes as coming from the
        // SPA frontend (matched against config('sanctum.stateful') via Referer/Origin),
        // and the test client only attaches cookies to JSON requests with credentials on
        // (mirroring the SPA's axios `withCredentials: true`).
        $this->withHeader('Referer', 'http://localhost:8000');
        $this->withCredentials();
    }

    public function test_can_log_in_with_valid_credentials(): void
    {
        $user = User::factory()->create(['password' => Hash::make('secret123')]);
        $this->branch->users()->attach($user->id, ['is_default' => true]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('secret123')]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('email');
        $this->assertGuest();
    }

    public function test_login_is_rejected_for_a_disabled_account(): void
    {
        $user = User::factory()->create(['password' => Hash::make('secret123'), 'status' => false]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('email');
        $this->assertGuest();
    }

    public function test_login_locks_out_after_repeated_failed_attempts(): void
    {
        $user = User::factory()->create(['password' => Hash::make('secret123')]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ])->assertStatus(422);
        }

        // 6th attempt is throttled even though nothing has expired.
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('email');
        $this->assertMatchesRegularExpression(
            '/^Too many attempts\. Try again in \d+s\.$/',
            $response->json('errors.email.0'),
        );
        $this->assertGuest();
    }

    public function test_successful_login_clears_the_rate_limiter(): void
    {
        $user = User::factory()->create(['password' => Hash::make('secret123')]);
        $key = 'login:'.strtolower($user->email).'|127.0.0.1';

        RateLimiter::hit($key);
        $this->assertTrue(RateLimiter::attempts($key) > 0);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ])->assertOk();

        $this->assertSame(0, RateLimiter::attempts($key));
    }

    public function test_logout_invalidates_the_session(): void
    {
        $this->actingAsSuperAdmin($this->branch);

        $response = $this->postJson('/api/v1/auth/logout');

        $response->assertOk();
        // The `auth:sanctum` middleware sets 'sanctum' as the request's default guard,
        // whose RequestGuard caches its resolved user independently of the 'web'
        // session guard AuthController::logout() actually logs out — assert that one.
        $this->assertGuest('web');
    }

    public function test_forgot_password_sends_a_reset_notification_for_a_known_email(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $response = $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_forgot_password_gives_the_same_response_for_an_unknown_email(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.com']);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        Notification::assertNothingSent();
    }

    public function test_can_reset_password_with_a_valid_token(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email]);

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token) {
            $token = $notification->token;

            return true;
        });

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'BrandNewPassword123',
            'password_confirmation' => 'BrandNewPassword123',
        ]);

        $response->assertOk();
        $this->assertTrue(Hash::check('BrandNewPassword123', $user->fresh()->password));
    }

    public function test_reset_password_rejects_an_invalid_token(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token' => 'not-a-real-token',
            'email' => $user->email,
            'password' => 'BrandNewPassword123',
            'password_confirmation' => 'BrandNewPassword123',
        ]);

        $response->assertStatus(422);
    }

    public function test_can_change_password_when_current_password_is_correct(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);
        $this->branch->users()->attach($user->id);
        $this->actingAs($user);

        $response = $this->postJson('/api/v1/auth/change-password', [
            'current_password' => 'old-password',
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ]);

        $response->assertOk();
        $this->assertTrue(Hash::check('NewPassword123', $user->fresh()->password));
    }

    public function test_change_password_rejects_an_incorrect_current_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);
        $this->actingAs($user);

        $response = $this->postJson('/api/v1/auth/change-password', [
            'current_password' => 'totally-wrong',
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ]);

        $response->assertStatus(422);
        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    public function test_lists_active_sessions_for_the_current_user(): void
    {
        $user = $this->actingAsSuperAdmin($this->branch);

        DB::table('sessions')->insert([
            'id' => 'other-session-id',
            'user_id' => $user->id,
            'ip_address' => '10.0.0.5',
            'user_agent' => 'PHPUnit',
            'last_activity' => now()->timestamp,
            'payload' => base64_encode(serialize([])),
        ]);

        $response = $this->getJson('/api/v1/auth/sessions');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    public function test_can_revoke_another_session_but_not_the_current_one(): void
    {
        $user = $this->actingAsSuperAdmin($this->branch);

        // Pin the "current" session id via cookie so it's stable across requests
        // (the array driver otherwise mints a fresh id per request).
        $currentSessionId = str_repeat('a', 40);
        $this->withCookie(config('session.cookie'), $currentSessionId);

        DB::table('sessions')->insert([
            'id' => 'other-session-id',
            'user_id' => $user->id,
            'ip_address' => '10.0.0.5',
            'user_agent' => 'PHPUnit',
            'last_activity' => now()->timestamp,
            'payload' => base64_encode(serialize([])),
        ]);

        $this->deleteJson('/api/v1/auth/sessions/'.$currentSessionId)
            ->assertStatus(422)
            ->assertJson(['success' => false, 'error_code' => 'CANNOT_REVOKE_CURRENT']);

        $this->assertDatabaseHas('sessions', ['id' => 'other-session-id']);

        $this->deleteJson('/api/v1/auth/sessions/other-session-id')->assertOk();

        $this->assertDatabaseMissing('sessions', ['id' => 'other-session-id']);
    }

    public function test_unauthenticated_requests_to_protected_routes_are_rejected(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(401);
        $response->assertJson(['success' => false, 'error_code' => 'UNAUTHENTICATED']);
    }

    public function test_unauthenticated_requests_without_an_explicit_json_accept_header_still_get_a_clean_401(): void
    {
        // No Accept header at all — this is what a plain browser navigation or a client that
        // doesn't set one looks like. Without redirectGuestsTo(null) in bootstrap/app.php, the
        // "auth" middleware tries to build a redirect to a route named 'login' (which doesn't
        // exist in this API-only app) and 500s instead of returning 401.
        $response = $this->get('/api/v1/auth/me');

        $response->assertStatus(401);
    }
}
