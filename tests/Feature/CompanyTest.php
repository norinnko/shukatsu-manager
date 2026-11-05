<?php

namespace Tests\Feature;

use App\Enums\CompanyStage;
use App\Models\Company;
use App\Models\EsAnswer;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CompanyTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_can_be_created(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('companies.store'), [
            'name' => '株式会社テスト',
            'phase' => 'intern',
            'stage' => 'es',
            'priority' => 'first',
            'tags' => ['セキュリティ'],
            'mypage_url' => 'https://example.com/mypage',
            'mypage_login_id' => 'abc123',
        ])->assertRedirect();

        $company = $user->companies()->sole();
        $this->assertSame(CompanyStage::Es, $company->stage);
        $this->assertSame(['セキュリティ'], $company->tags);
    }

    public function test_javascript_urls_are_rejected(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('companies.store'), [
                'name' => 'X',
                'phase' => 'intern',
                'stage' => 'es',
                'mypage_url' => 'javascript:alert(1)',
            ])
            ->assertSessionHasErrors('mypage_url');
    }

    public function test_company_names_are_unique_per_user(): void
    {
        $user = User::factory()->create();
        Company::factory()->for($user)->create(['name' => 'A社']);
        Company::factory()->create(['name' => 'B社']);

        $this->actingAs($user)
            ->post(route('companies.store'), ['name' => 'A社', 'phase' => 'intern', 'stage' => 'es'])
            ->assertSessionHasErrors('name');

        $this->actingAs($user)
            ->post(route('companies.store'), ['name' => 'B社', 'phase' => 'intern', 'stage' => 'es'])
            ->assertSessionHasNoErrors();
    }

    public function test_stage_can_be_moved(): void
    {
        $company = Company::factory()->create();

        $this->actingAs($company->user)
            ->patch(route('companies.stage', $company), ['stage' => 'interview'])
            ->assertRedirect();

        $this->assertSame(CompanyStage::Interview, $company->fresh()->stage);
    }

    public function test_other_users_companies_are_not_found(): void
    {
        $company = Company::factory()->create();
        $intruder = User::factory()->create();

        $this->actingAs($intruder)->get(route('companies.show', $company))->assertNotFound();
        $this->actingAs($intruder)->patch(route('companies.stage', $company), ['stage' => 'offer'])->assertNotFound();
        $this->actingAs($intruder)->delete(route('companies.destroy', $company))->assertNotFound();

        $this->assertModelExists($company);
    }

    public function test_archived_companies_are_hidden_by_default(): void
    {
        $user = User::factory()->create();
        Company::factory()->for($user)->create(['name' => 'visible']);
        Company::factory()->for($user)->create(['name' => 'archived', 'archived_at' => now()]);

        $this->actingAs($user)->get(route('companies.index'))
            ->assertInertia(fn (Assert $page) => $page->has('companies', 1)->where('companies.0.name', 'visible'));

        $this->actingAs($user)->get(route('companies.index', ['archived' => 1]))
            ->assertInertia(fn (Assert $page) => $page->has('companies', 2));
    }

    public function test_deleting_a_company_removes_its_events_but_keeps_es_answers(): void
    {
        $company = Company::factory()->create();
        $event = Event::factory()->for($company->user)->create(['company_id' => $company->id]);
        $answer = $company->user->esAnswers()->create([
            'company_id' => $company->id,
            'category' => 'self_pr',
            'question' => 'Q',
            'answer' => 'A',
        ]);

        $this->actingAs($company->user)->delete(route('companies.destroy', $company))->assertRedirect(route('companies.index'));

        $this->assertModelMissing($company);
        $this->assertModelMissing($event);
        $this->assertNull(EsAnswer::find($answer->id)->company_id);
    }
}
