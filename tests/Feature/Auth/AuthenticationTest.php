<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['shukatsu.allowed_emails' => ['me@example.com']]);
    }

    private function fakeGoogleUser(string $email, bool $verified = true, string $id = 'google-123'): void
    {
        $googleUser = (new SocialiteUser)->map([
            'id' => $id,
            'name' => 'Test User',
            'email' => $email,
            'avatar' => 'https://example.com/a.png',
        ])->setRaw(['email_verified' => $verified]);

        $provider = Mockery::mock(GoogleProvider::class);
        $provider->shouldReceive('user')->andReturn($googleUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    public function test_login_screen_can_be_rendered(): void
    {
        $this->get(route('login'))->assertOk();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_allowed_google_account_can_sign_in(): void
    {
        $this->fakeGoogleUser('Me@Example.com');

        $this->get(route('auth.google.callback'))->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
        $this->assertSame('google-123', User::first()->google_id);
    }

    public function test_other_google_accounts_are_rejected(): void
    {
        $this->fakeGoogleUser('stranger@example.com');

        $this->get(route('auth.google.callback'))->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_unverified_google_email_is_rejected(): void
    {
        $this->fakeGoogleUser('me@example.com', verified: false);

        $this->get(route('auth.google.callback'))->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_account_bound_to_another_google_identity_is_rejected(): void
    {
        User::factory()->create(['email' => 'me@example.com'])->forceFill(['google_id' => 'original'])->save();
        $this->fakeGoogleUser('me@example.com', id: 'attacker');

        $this->get(route('auth.google.callback'))->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_dev_login_is_unavailable_outside_local(): void
    {
        config(['shukatsu.dev_login' => true]);
        User::factory()->create(['email' => 'me@example.com']);

        // phpunit runs with APP_ENV=testing, not local.
        $this->post(route('dev-login'))->assertNotFound();
        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
