<?php

use App\Http\Controllers\Settings\GoogleConnectionController;
use App\Http\Controllers\Settings\LineSettingsController;
use App\Http\Controllers\Settings\MailRuleController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::redirect('settings', '/settings/integrations');

    Route::get('settings/integrations', [GoogleConnectionController::class, 'edit'])->name('integrations.edit');
    Route::get('settings/google/redirect', [GoogleConnectionController::class, 'redirect'])->name('google.connect');
    Route::get('settings/google/callback', [GoogleConnectionController::class, 'callback'])->name('google.callback');
    Route::put('settings/google/calendar', [GoogleConnectionController::class, 'updateCalendar'])->name('google.calendar.update');
    Route::post('settings/gmail/sync', [GoogleConnectionController::class, 'syncGmail'])->name('gmail.sync');
    Route::delete('settings/mail-rules/{mailRule}', [MailRuleController::class, 'destroy'])->name('mail-rules.destroy');
    Route::put('settings/line', [LineSettingsController::class, 'update'])->name('line.update');
    Route::post('settings/line/test', [LineSettingsController::class, 'test'])->name('line.test');
    Route::delete('settings/google', [GoogleConnectionController::class, 'destroy'])->name('google.disconnect');

    Route::inertia('settings/appearance', 'settings/appearance')->name('appearance.edit');
});
