<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

/**
 * Google-only sign in, restricted to the emails in ALLOWED_LOGIN_EMAILS.
 */
class AuthController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('auth/login', [
            'googleConfigured' => filled(config('services.google.client_id')),
            'devLogin' => self::devLoginEnabled(),
            'error' => $request->session()->get('login_error'),
        ]);
    }

    public function redirect(): SymfonyRedirect
    {
        return Socialite::driver('google')
            ->with(['prompt' => 'select_account'])
            ->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable $e) {
            Log::warning('Google login failed', ['error' => $e->getMessage()]);

            return $this->fail('Googleログインに失敗しました。もう一度お試しください。');
        }

        $email = strtolower((string) $googleUser->getEmail());
        $verified = (bool) ($googleUser->user['email_verified'] ?? false);

        if (! $verified || ! in_array($email, config('shukatsu.allowed_emails'), true)) {
            return $this->fail('このGoogleアカウントではログインできません。');
        }

        $user = User::firstOrNew(['email' => $email]);

        // The account was already bound to a different Google identity.
        if ($user->google_id !== null && $user->google_id !== $googleUser->getId()) {
            return $this->fail('このGoogleアカウントではログインできません。');
        }

        $user->fill([
            'name' => $user->name ?? $googleUser->getName() ?? $email,
            'avatar' => $googleUser->getAvatar(),
        ]);
        $user->forceFill(['google_id' => $googleUser->getId()])->save();

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Local-only shortcut so the app can be developed before Google OAuth
     * credentials exist.
     */
    public function devLogin(Request $request): RedirectResponse
    {
        abort_unless(self::devLoginEnabled(), 404);

        $email = config('shukatsu.allowed_emails')[0] ?? null;
        $user = ($email ? User::where('email', $email)->first() : null) ?? User::query()->oldest('id')->first();

        if ($user === null) {
            return $this->fail('ユーザーがいません。先に php artisan db:seed を実行してください。');
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public static function devLoginEnabled(): bool
    {
        return app()->isLocal() && config('shukatsu.dev_login');
    }

    private function fail(string $message): RedirectResponse
    {
        return redirect()->route('login')->with('login_error', $message);
    }
}
