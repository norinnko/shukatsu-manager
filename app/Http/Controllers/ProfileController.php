<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The "copy & paste" profile used to fill in company mypages.
 */
class ProfileController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('profile/index', [
            'profile' => $user->profile ?? (object) [],
            'educations' => $user->educations()->orderBy('entered_on')->get(),
            'certifications' => $user->certifications()->orderByRaw('acquired_on is null, acquired_on')->get(),
        ]);
    }

    public function update(ProfileRequest $request): RedirectResponse
    {
        $request->user()->profile()->updateOrCreate([], $request->validated());

        $this->toast('基本情報を保存しました。');

        return back();
    }
}
