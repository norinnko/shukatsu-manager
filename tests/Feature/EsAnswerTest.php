<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EsAnswerTest extends TestCase
{
    use RefreshDatabase;

    public function test_answer_can_be_saved_and_listed(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->for($user)->create();

        $this->actingAs($user)->post(route('es.store'), [
            'company_id' => $company->id,
            'category' => 'self_pr',
            'question' => '自己PR',
            'answer' => '私の強みは…',
            'char_limit' => 400,
        ])->assertSessionHasNoErrors();

        $this->actingAs($user)->get(route('es.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('es/index')
                ->has('answers', 1)
                ->where('answers.0.char_limit', 400)
                ->where('answers.0.company.id', $company->id));
    }

    public function test_answers_of_other_users_cannot_be_edited(): void
    {
        $owner = User::factory()->create();
        $answer = $owner->esAnswers()->create(['category' => 'other', 'question' => 'Q', 'answer' => 'A']);

        $this->actingAs(User::factory()->create())
            ->put(route('es.update', $answer), ['category' => 'other', 'question' => 'hacked', 'answer' => 'x'])
            ->assertNotFound();

        $this->assertSame('Q', $answer->fresh()->question);
    }
}
