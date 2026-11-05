<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\CertificationController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EducationController;
use App\Http\Controllers\EsAnswerController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\InboxController;
use App\Http\Controllers\LineWebhookController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');
Route::view('privacy', 'privacy')->name('privacy');
Route::post('line/webhook', LineWebhookController::class)->name('line.webhook');

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'create'])->name('login');
    Route::get('auth/google/redirect', [AuthController::class, 'redirect'])->name('auth.google.redirect');
    Route::get('auth/google/callback', [AuthController::class, 'callback'])->name('auth.google.callback');
    Route::post('dev-login', [AuthController::class, 'devLogin'])->name('dev-login');
});

Route::post('logout', [AuthController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::resource('companies', CompanyController::class)->except(['create', 'edit']);
    Route::patch('companies/{company}/stage', [CompanyController::class, 'updateStage'])->name('companies.stage');

    Route::get('calendar', [EventController::class, 'index'])->name('calendar.index');
    Route::post('events', [EventController::class, 'store'])->name('events.store');
    Route::put('events/{event}', [EventController::class, 'update'])->name('events.update');
    Route::patch('events/{event}/status', [EventController::class, 'updateStatus'])->name('events.status');
    Route::post('events/{event}/sync', [EventController::class, 'sync'])->name('events.sync');
    Route::delete('events/{event}', [EventController::class, 'destroy'])->name('events.destroy');

    Route::get('inbox', [InboxController::class, 'index'])->name('inbox.index');
    Route::patch('inbox/{email}/status', [InboxController::class, 'updateStatus'])->name('inbox.status');
    Route::post('inbox/{email}/hide-similar', [InboxController::class, 'hideSimilar'])->name('inbox.hide-similar');
    Route::post('inbox/{email}/actions/{index}/event', [InboxController::class, 'storeEvent'])->whereNumber('index')->name('inbox.actions.event');
    Route::post('inbox/{email}/actions/{index}/apply', [InboxController::class, 'applyAction'])->whereNumber('index')->name('inbox.actions.apply');
    Route::post('inbox/assess', [InboxController::class, 'assessNow'])->name('inbox.assess');

    Route::get('es', [EsAnswerController::class, 'index'])->name('es.index');
    Route::post('es', [EsAnswerController::class, 'store'])->name('es.store');
    Route::put('es/{esAnswer}', [EsAnswerController::class, 'update'])->name('es.update');
    Route::delete('es/{esAnswer}', [EsAnswerController::class, 'destroy'])->name('es.destroy');

    Route::get('profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('profile/educations', [EducationController::class, 'store'])->name('educations.store');
    Route::put('profile/educations/{education}', [EducationController::class, 'update'])->name('educations.update');
    Route::delete('profile/educations/{education}', [EducationController::class, 'destroy'])->name('educations.destroy');
    Route::post('profile/certifications', [CertificationController::class, 'store'])->name('certifications.store');
    Route::put('profile/certifications/{certification}', [CertificationController::class, 'update'])->name('certifications.update');
    Route::delete('profile/certifications/{certification}', [CertificationController::class, 'destroy'])->name('certifications.destroy');
});

require __DIR__.'/settings.php';
