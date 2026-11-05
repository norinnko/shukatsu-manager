<?php

namespace App\Http\Controllers;

use App\Enums\CompanyStage;
use App\Http\Requests\CompanyRequest;
use App\Http\Resources\CompanyResource;
use App\Http\Resources\EsAnswerResource;
use App\Http\Resources\EventResource;
use App\Models\Company;
use App\Services\Google\GoogleCalendarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CompanyController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'archived' => ['nullable', 'boolean'],
        ]);

        $companies = $request->user()->companies()
            ->when(! ($filters['archived'] ?? false), fn ($q) => $q->active())
            ->when($filters['q'] ?? null, fn ($q, $search) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('industry', 'like', "%{$search}%")
                ->orWhere('memo', 'like', "%{$search}%")))
            ->with(['events' => fn ($q) => $q->upcoming()->orderBy('starts_at')->limit(1)])
            ->withCount('esAnswers')
            ->orderBy('name')
            ->get();

        return Inertia::render('companies/index', [
            'companies' => CompanyResource::collection($companies)->resolve(),
            'filters' => [
                'q' => $filters['q'] ?? '',
                'archived' => (bool) ($filters['archived'] ?? false),
            ],
        ]);
    }

    public function store(CompanyRequest $request): RedirectResponse
    {
        $company = $request->user()->companies()->create($request->companyData());

        $this->toast("「{$company->name}」を追加しました。");

        return redirect()->route('companies.show', $company);
    }

    public function show(Request $request, Company $company): Response
    {
        Gate::authorize('own', $company);

        $company->load([
            'events' => fn ($q) => $q->orderByDesc('starts_at'),
            'esAnswers' => fn ($q) => $q->latest('updated_at'),
        ]);

        return Inertia::render('companies/show', [
            'company' => (new CompanyResource($company))->resolve(),
            'events' => EventResource::collection($company->events)->resolve(),
            'esAnswers' => EsAnswerResource::collection($company->esAnswers)->resolve(),
            'googleConnected' => $request->user()->hasGoogleCalendar(),
        ]);
    }

    public function update(CompanyRequest $request, Company $company): RedirectResponse
    {
        Gate::authorize('own', $company);

        $company->update($request->companyData());

        $this->toast('保存しました。');

        return back();
    }

    /**
     * Move a company to another column on the board.
     */
    public function updateStage(Request $request, Company $company): RedirectResponse
    {
        Gate::authorize('own', $company);

        $data = $request->validate(['stage' => ['required', Rule::enum(CompanyStage::class)]]);

        $company->update($data);

        $this->toast("「{$company->name}」を「{$company->stage->label()}」に移動しました。");

        return back();
    }

    public function destroy(Company $company, GoogleCalendarService $calendar): RedirectResponse
    {
        Gate::authorize('own', $company);

        // Events belong to the company; ES answers stay in the library.
        foreach ($company->events as $event) {
            $calendar->delete($event);
            $event->delete();
        }

        $company->delete();

        $this->toast("「{$company->name}」を削除しました。");

        return redirect()->route('companies.index');
    }
}
