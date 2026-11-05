<?php

namespace App\Http\Controllers;

use App\Enums\EsCategory;
use App\Http\Requests\EsAnswerRequest;
use App\Http\Resources\EsAnswerResource;
use App\Models\EsAnswer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EsAnswerController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', Rule::enum(EsCategory::class)],
        ]);

        $user = $request->user();

        $answers = $user->esAnswers()
            ->with('company:id,name')
            ->when($filters['category'] ?? null, fn ($q, $category) => $q->where('category', $category))
            ->when($filters['q'] ?? null, fn ($q, $search) => $q->where(fn ($q) => $q
                ->where('question', 'like', "%{$search}%")
                ->orWhere('answer', 'like', "%{$search}%")))
            ->latest('updated_at')
            ->get();

        return Inertia::render('es/index', [
            'answers' => EsAnswerResource::collection($answers)->resolve(),
            'companies' => $user->companies()->orderBy('name')->get(['id', 'name']),
            'filters' => [
                'q' => $filters['q'] ?? '',
                'category' => $filters['category'] ?? '',
            ],
        ]);
    }

    public function store(EsAnswerRequest $request): RedirectResponse
    {
        $request->user()->esAnswers()->create($request->validated());

        $this->toast('ES回答を保存しました。');

        return back();
    }

    public function update(EsAnswerRequest $request, EsAnswer $esAnswer): RedirectResponse
    {
        Gate::authorize('own', $esAnswer);

        $esAnswer->update($request->validated());

        $this->toast('ES回答を更新しました。');

        return back();
    }

    public function destroy(EsAnswer $esAnswer): RedirectResponse
    {
        Gate::authorize('own', $esAnswer);

        $esAnswer->delete();

        $this->toast('ES回答を削除しました。');

        return back();
    }
}
