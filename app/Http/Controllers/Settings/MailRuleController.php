<?php

namespace App\Http\Controllers\Settings;

use App\Enums\EmailStatus;
use App\Http\Controllers\Controller;
use App\Models\MailRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class MailRuleController extends Controller
{
    /**
     * Delete a "次から表示しない" rule; mails it hid come back to the inbox.
     */
    public function destroy(MailRule $mailRule): RedirectResponse
    {
        Gate::authorize('own', $mailRule);

        $restored = $mailRule->emails()->where('status', EmailStatus::Excluded)->update(['status' => EmailStatus::New]);
        $mailRule->delete();

        $this->toast($restored > 0
            ? "ルールを削除し、除外していた{$restored}件を受信箱に戻しました。"
            : 'ルールを削除しました。');

        return back();
    }
}
