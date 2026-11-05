<?php

namespace App\Services\Google;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Keeps the user's Google OAuth access token fresh using the stored
 * refresh token.
 */
class GoogleTokenService
{
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const REVOKE_URL = 'https://oauth2.googleapis.com/revoke';

    public function accessToken(User $user): string
    {
        if ($user->google_refresh_token === null) {
            throw new GoogleNotConnectedException('Googleアカウントが連携されていません。');
        }

        $expiresAt = $user->google_token_expires_at;

        if ($user->google_access_token !== null && $expiresAt !== null && $expiresAt->isAfter(now()->addMinute())) {
            return $user->google_access_token;
        }

        return $this->refresh($user);
    }

    public function refresh(User $user): string
    {
        $response = Http::asForm()->post(self::TOKEN_URL, [
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'refresh_token' => $user->google_refresh_token,
            'grant_type' => 'refresh_token',
        ]);

        if ($response->json('error') === 'invalid_grant') {
            // Refresh token was revoked or expired: the user must reconnect.
            $this->forget($user);

            throw new GoogleNotConnectedException('Googleとの連携が切れました。設定画面から再連携してください。');
        }

        if ($response->failed()) {
            throw new RuntimeException('Googleのアクセストークン更新に失敗しました: '.$response->status());
        }

        $user->forceFill([
            'google_access_token' => $response->json('access_token'),
            'google_token_expires_at' => now()->addSeconds((int) $response->json('expires_in', 3600)),
        ])->save();

        return (string) $response->json('access_token');
    }

    /**
     * Revoke the grant at Google and clear the stored tokens.
     */
    public function disconnect(User $user): void
    {
        if ($user->google_refresh_token !== null) {
            Http::asForm()->post(self::REVOKE_URL, ['token' => $user->google_refresh_token]);
        }

        $this->forget($user);
    }

    private function forget(User $user): void
    {
        $user->forceFill([
            'google_access_token' => null,
            'google_refresh_token' => null,
            'google_token_expires_at' => null,
            'google_scopes' => null,
        ])->save();
    }
}
