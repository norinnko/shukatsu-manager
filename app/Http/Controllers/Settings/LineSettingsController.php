<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\Line\LineClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class LineSettingsController extends Controller
{
    /**
     * Save the LINE user to notify ("あなたのユーザーID" in LINE Developers).
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'line_user_id' => ['nullable', 'string', 'regex:/^U[0-9a-f]{32}$/'],
        ], [
            'line_user_id.regex' => 'LINEのユーザーIDは「U」で始まる33文字です（LINE Developers の「チャネル基本設定」→「あなたのユーザーID」）。',
        ]);

        $user = $request->user();
        $lineUserId = $validated['line_user_id'] ?? null;

        if ($lineUserId !== $user->line_user_id) {
            // Only mails arriving from now on are pushed.
            $user->forceFill(['line_user_id' => $lineUserId, 'line_linked_at' => $lineUserId ? now() : null])->save();
        }

        $this->toast($lineUserId ? 'LINEの送り先を保存しました。' : 'LINEの送り先を解除しました。');

        return back();
    }

    /**
     * Push a test message (counts as one of the month's pushes).
     */
    public function test(Request $request, LineClient $line): RedirectResponse
    {
        $user = $request->user();

        try {
            $line->push((string) $user->line_user_id, [['type' => 'text', 'text' => '就活マネージャーとつながりました。朝のまとめと大事なメールをお知らせします。']]);
            $remaining = $line->remainingPushes();
            $this->toast('テストメッセージを送りました。'.($remaining !== null ? "今月の残り：{$remaining}通" : ''));
        } catch (Throwable $e) {
            $this->toast($e->getMessage(), 'error');
        }

        return back();
    }
}
