<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Gmail\MailActionException;
use App\Services\Gmail\MailActionService;
use App\Services\Line\LineClient;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Receives LINE events. Only requests signed with the channel secret and
 * events from the linked user's LINE account are acted on.
 */
class LineWebhookController extends Controller
{
    public function __invoke(Request $request, LineClient $line, MailActionService $actions): Response
    {
        if (! $line->validSignature($request->getContent(), $request->header('X-Line-Signature'))) {
            return response('invalid signature', 400);
        }

        foreach ((array) $request->input('events', []) as $event) {
            $userId = $event['source']['userId'] ?? null;
            $user = is_string($userId) ? User::where('line_user_id', $userId)->first() : null;
            $replyToken = $event['replyToken'] ?? null;

            if ($user === null || ! is_string($replyToken)) {
                continue;
            }

            $text = match ($event['type'] ?? null) {
                'postback' => $this->postback($user, (string) ($event['postback']['data'] ?? ''), $actions),
                'message', 'follow' => '就活マネージャーです。大事なメールが届くとお知らせします。'."\n受信箱：".route('inbox.index'),
                default => null,
            };

            if ($text !== null) {
                try {
                    $line->reply($replyToken, [['type' => 'text', 'text' => $text]]);
                } catch (Throwable $e) {
                    Log::warning('LINE reply failed', ['error' => $e->getMessage()]);
                }
            }
        }

        return response('ok');
    }

    /**
     * A button on a mail card: add / apply the calendar action or ignore.
     */
    private function postback(User $user, string $data, MailActionService $actions): string
    {
        parse_str($data, $params);
        $email = $user->emails()->find((int) ($params['email'] ?? 0));

        if ($email === null) {
            return 'メールが見つかりませんでした。';
        }

        $index = (int) ($params['index'] ?? -1);

        try {
            return match ($params['op'] ?? null) {
                'add' => $actions->createAsSuggested($email, $index)[1],
                'apply' => $actions->apply($email, $index)[1],
                'ignore' => tap('無視しました。', fn () => $actions->ignore($email)),
                default => '分からない操作です。',
            };
        } catch (MailActionException $e) {
            return $e->getMessage();
        }
    }
}
