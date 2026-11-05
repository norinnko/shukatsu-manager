<?php

namespace App\Http\Controllers;

use App\Http\Requests\EducationRequest;
use App\Models\Education;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class EducationController extends Controller
{
    public function store(EducationRequest $request): RedirectResponse
    {
        $request->user()->educations()->create($request->validated());

        $this->toast('学歴を追加しました。');

        return back();
    }

    public function update(EducationRequest $request, Education $education): RedirectResponse
    {
        Gate::authorize('own', $education);

        $education->update($request->validated());

        $this->toast('学歴を更新しました。');

        return back();
    }

    public function destroy(Education $education): RedirectResponse
    {
        Gate::authorize('own', $education);

        $education->delete();

        $this->toast('学歴を削除しました。');

        return back();
    }
}
