<?php

namespace App\Http\Middleware;

use App\Enums\CompanyPhase;
use App\Enums\CompanyPriority;
use App\Enums\CompanyStage;
use App\Enums\EmailStatus;
use App\Enums\EsCategory;
use App\Enums\EventStatus;
use App\Enums\EventType;
use App\Enums\MailCategory;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                // Only what the UI needs; never tokens.
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $user->avatar,
                ] : null,
            ],
            'enums' => fn () => [
                'stages' => CompanyStage::options(),
                'phases' => CompanyPhase::options(),
                'priorities' => CompanyPriority::options(),
                'eventTypes' => EventType::options(),
                'eventStatuses' => EventStatus::options(),
                'esCategories' => EsCategory::options(),
                'emailStatuses' => EmailStatus::options(),
                'mailCategories' => MailCategory::options(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
