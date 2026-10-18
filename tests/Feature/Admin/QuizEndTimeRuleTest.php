<?php

namespace Tests\Feature\Admin;

use App\Models\Quiz;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuizEndTimeRuleTest extends TestCase
{
    use RefreshDatabase;

    private function payload(Quiz $quiz, array $over = []): array
    {
        return array_merge([
            'title' => $quiz->title, 'status' => $quiz->status, 'point_cost' => 1,
            'max_official_attempts' => 1, 'starts_at' => now()->subDays(3)->format('Y-m-d\TH:i'),
            'ends_at' => $quiz->ends_at?->format('Y-m-d\TH:i'),
        ], $over);
    }

    public function test_a_live_quiz_cannot_be_given_an_end_time_in_the_past(): void
    {
        $quiz = Quiz::factory()->create();
        $past = now()->subHour()->format('Y-m-d\TH:i');

        $this->actingAs($this->makeAdmin())->put(route('admin.quizzes.update', $quiz), $this->payload($quiz, ['ends_at' => $past]))
            ->assertSessionHasErrors('ends_at');
    }

    public function test_a_few_minutes_grace_and_future_times_are_fine(): void
    {
        $quiz = Quiz::factory()->create();
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->put(route('admin.quizzes.update', $quiz), $this->payload($quiz, ['ends_at' => now()->subMinutes(2)->format('Y-m-d\TH:i')]))
            ->assertSessionDoesntHaveErrors('ends_at');
        $this->actingAs($admin)->put(route('admin.quizzes.update', $quiz), $this->payload($quiz, ['ends_at' => now()->addDay()->format('Y-m-d\TH:i')]))
            ->assertSessionDoesntHaveErrors('ends_at');
    }

    public function test_editing_a_finished_quiz_without_touching_its_end_time_is_never_blocked(): void
    {
        $quiz = Quiz::factory()->create(['starts_at' => now()->subDays(3), 'ends_at' => now()->subDay()]);

        $this->actingAs($this->makeAdmin())->put(route('admin.quizzes.update', $quiz), $this->payload($quiz, ['title' => 'নতুন নাম']))
            ->assertSessionDoesntHaveErrors();
        $this->assertSame('নতুন নাম', $quiz->fresh()->title);
    }

    public function test_drafts_and_archived_quizzes_are_exempt(): void
    {
        $quiz = Quiz::factory()->create();
        $past = now()->subDays(2)->format('Y-m-d\TH:i');

        foreach ([Quiz::STATUS_DRAFT, Quiz::STATUS_ARCHIVED] as $status) {
            $this->actingAs($this->makeAdmin())->put(route('admin.quizzes.update', $quiz), $this->payload($quiz, ['status' => $status, 'ends_at' => $past]))
                ->assertSessionDoesntHaveErrors('ends_at');
        }
    }
}
