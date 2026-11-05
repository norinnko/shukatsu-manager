<?php

namespace App\Http\Controllers;

use App\Http\Requests\CertificationRequest;
use App\Models\Certification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class CertificationController extends Controller
{
    public function store(CertificationRequest $request): RedirectResponse
    {
        $request->user()->certifications()->create($request->validated());

        $this->toast('資格を追加しました。');

        return back();
    }

    public function update(CertificationRequest $request, Certification $certification): RedirectResponse
    {
        Gate::authorize('own', $certification);

        $certification->update($request->validated());

        $this->toast('資格を更新しました。');

        return back();
    }

    public function destroy(Certification $certification): RedirectResponse
    {
        Gate::authorize('own', $certification);

        $certification->delete();

        $this->toast('資格を削除しました。');

        return back();
    }
}
