<?php

namespace Tests\Feature\Student;

use App\Models\Course;
use App\Models\Quiz;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExamSearchTest extends TestCase
{
    use RefreshDatabase;

    private function exams(): array
    {
        $seerat = Course::factory()->create(['title' => 'সীরাত']);
        $tafseer = Course::factory()->create(['title' => 'তাফসির']);
        $past = ['status' => Quiz::STATUS_PUBLISHED, 'starts_at' => now()->subDays(3), 'ends_at' => now()->subDay()];
        Quiz::factory()->create($past + ['title' => 'সীরাত-৩০', 'course_id' => $seerat->id]);
        Quiz::factory()->create($past + ['title' => 'সীরাত-৩১', 'course_id' => $seerat->id]);
        Quiz::factory()->create($past + ['title' => 'তাফসির-০১', 'course_id' => $tafseer->id]);

        return [$seerat, $tafseer];
    }

    public function test_the_exam_page_has_a_search_box_and_a_course_picker(): void
    {
        $this->exams();

        $this->actingAs($this->makeStudent())->get(route('student.exams.index'))
            ->assertOk()
            ->assertSee('role="search"', false)
            ->assertSee(__('exams.search_all_courses'));
    }

    public function test_searching_by_name_accepts_latin_digits_and_no_hyphen(): void
    {
        $this->exams();

        $this->actingAs($this->makeStudent())->get(route('student.exams.index', ['q' => 'সীরাত 30']))
            ->assertOk()
            ->assertSee('সীরাত-৩০')
            ->assertDontSee('সীরাত-৩১')
            ->assertDontSee('তাফসির-০১');
    }

    public function test_the_course_picker_shows_only_that_courses_exams(): void
    {
        [, $tafseer] = $this->exams();

        $this->actingAs($this->makeStudent())->get(route('student.exams.index', ['course' => $tafseer->id]))
            ->assertOk()
            ->assertSee('তাফসির-০১')
            ->assertDontSee('সীরাত-৩০');
    }

    public function test_no_match_says_so_and_offers_the_full_list(): void
    {
        $this->exams();

        $this->actingAs($this->makeStudent())->get(route('student.exams.index', ['q' => 'হাদিস']))
            ->assertOk()
            ->assertSee(__('exams.search_none'))
            ->assertSee(__('exams.search_clear'));
    }
}
