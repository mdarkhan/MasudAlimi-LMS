<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\PointTransaction;
use App\Models\Quiz;
use App\Services\Points\PointService;
use App\Support\TableSort;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class TableSortTest extends TestCase
{
    use RefreshDatabase;

    private function sorted(array $values, string $dir = 'asc'): array
    {
        $sort = TableSort::from(Request::create('/', 'GET', ['sort' => 'v', 'dir' => $dir]), ['v'], 'v');

        return $sort->collection(collect($values), ['v' => fn ($x) => $x])->all();
    }

    public function test_bengali_numbers_sort_as_numbers_and_blanks_stay_last(): void
    {
        $this->assertSame(['সীরাত-২', 'সীরাত-৯', 'সীরাত-১০', 'সীরাত-২৮', null], $this->sorted(['সীরাত-১০', null, 'সীরাত-৯', 'সীরাত-২৮', 'সীরাত-২']));
        // descending still keeps the blank at the bottom
        $this->assertSame(['সীরাত-২৮', 'সীরাত-১০', 'সীরাত-৯', ''], $this->sorted(['সীরাত-৯', '', 'সীরাত-২৮', 'সীরাত-১০'], 'desc'));
        $this->assertSame([2, 10, 100], $this->sorted([100, 2, 10]));
    }

    public function test_ties_keep_their_original_order(): void
    {
        $sort = TableSort::from(Request::create('/', 'GET', ['sort' => 'score']), ['score'], 'score');
        $rows = collect([['id' => 'a', 'score' => 5], ['id' => 'b', 'score' => 3], ['id' => 'c', 'score' => 5]]);

        $this->assertSame(['b', 'a', 'c'], $sort->collection($rows, ['score' => fn ($r) => $r['score']])->pluck('id')->all());
    }

    public function test_unknown_sort_keys_and_directions_from_the_url_are_ignored(): void
    {
        $sort = TableSort::from(Request::create('/', 'GET', ['sort' => 'password; DROP TABLE users', 'dir' => 'sideways']), ['name'], 'name', 'desc');

        $this->assertSame('name', $sort->key);
        $this->assertSame('desc', $sort->dir);
    }

    public function test_the_admin_quiz_list_sorts_titles_naturally_across_pages(): void
    {
        $course = Course::factory()->create();
        foreach (range(1, 25) as $n) {   // 25 quizzes → two pages of 20
            Quiz::factory()->create(['title' => 'সীরাত-'.\App\Services\Import\BengaliText::toBengaliDigits($n), 'course_id' => $course->id]);
        }
        $admin = $this->makeSuperAdmin();

        $page1 = $this->actingAs($admin)->get(route('admin.quizzes.index', ['sort' => 'title', 'dir' => 'asc']))->assertOk();
        $page1->assertSeeInOrder(['সীরাত-১', 'সীরাত-২', 'সীরাত-৯', 'সীরাত-১০']);

        // descending: the highest number comes first, on page one
        $this->actingAs($admin)->get(route('admin.quizzes.index', ['sort' => 'title', 'dir' => 'desc']))->assertOk()
            ->assertSeeInOrder(['সীরাত-২৫', 'সীরাত-২৪']);
        // and the lowest lands on the last page
        $this->actingAs($admin)->get(route('admin.quizzes.index', ['sort' => 'title', 'dir' => 'desc', 'page' => 2]))->assertOk()
            ->assertSee('সীরাত-১<', false);
    }

    public function test_admin_results_by_exam_follow_the_exam_number_even_without_dates(): void
    {
        // imported exams can have no start date — they must not sink to the end
        $q7 = Quiz::factory()->create(['title' => 'সীরাত-০৭', 'starts_at' => now()->subDays(5), 'ends_at' => now()->subDays(4)]);
        $q4 = Quiz::factory()->create(['title' => 'সীরাত-০৪', 'starts_at' => null, 'ends_at' => null]);
        $q10 = Quiz::factory()->create(['title' => 'সীরাত-১০', 'starts_at' => now()->subDays(2), 'ends_at' => now()->subDay()]);
        foreach ([$q10, $q7, $q4] as $quiz) {
            \App\Models\QuizAttempt::factory()->create(['quiz_id' => $quiz->id, 'user_id' => $this->makeStudent()->id]);
        }

        $this->actingAs($this->makeSuperAdmin())->get(route('admin.results.index', ['sort' => 'quiz', 'dir' => 'asc']))->assertOk()
            ->assertSeeInOrder(['সীরাত-০৪', 'সীরাত-০৭', 'সীরাত-১০']);
        $this->actingAs($this->makeSuperAdmin())->get(route('admin.results.index', ['sort' => 'quiz', 'dir' => 'desc']))->assertOk()
            ->assertSeeInOrder(['সীরাত-১০', 'সীরাত-০৭', 'সীরাত-০৪']);
    }

    public function test_exam_pickers_and_lists_follow_the_exam_number(): void
    {
        $past = ['status' => Quiz::STATUS_PUBLISHED, 'results_released_at' => now()->subHour(), 'practice_enabled' => true, 'leaderboard_visible' => true];
        Quiz::factory()->create($past + ['title' => 'সীরাত-১০', 'starts_at' => now()->subDays(3), 'ends_at' => now()->subDays(2)]);
        Quiz::factory()->create($past + ['title' => 'সীরাত-০৯', 'starts_at' => null, 'ends_at' => null]);
        Quiz::factory()->create($past + ['title' => 'সীরাত-০৪', 'starts_at' => null, 'ends_at' => null]);

        // admin result filter dropdown
        $this->actingAs($this->makeSuperAdmin())->get(route('admin.results.index'))->assertOk()
            ->assertSeeInOrder(['>সীরাত-০৪<', '>সীরাত-০৯<', '>সীরাত-১০<'], false);

        // student exam list: undated (imported) exams first, in number order, then by date
        $this->actingAs($this->makeStudent())->get(route('student.exams.index'))->assertOk()
            ->assertSeeInOrder(['সীরাত-০৪', 'সীরাত-০৯', 'সীরাত-১০']);
    }

    public function test_admin_search_finds_a_roll_typed_in_bengali_digits(): void
    {
        $this->makeStudent(['roll' => '57', 'name' => 'সাতান্ন']);

        $this->actingAs($this->makeSuperAdmin())->get(route('admin.students.index', ['q' => '৫৭']))->assertOk()->assertSee('সাতান্ন');
        $this->actingAs($this->makeSuperAdmin())->get(route('admin.search.index', ['q' => '৫৭']))->assertOk()->assertSee('সাতান্ন');
    }

    public function test_a_student_sorts_their_point_history_by_amount(): void
    {
        $student = $this->makeStudent();
        $points = app(PointService::class);
        $points->credit($student, 3, PointTransaction::TYPE_GRANT, 'তিন');
        $points->credit($student, 10, PointTransaction::TYPE_GRANT, 'দশ');
        $points->credit($student, 1, PointTransaction::TYPE_GRANT, 'এক');

        $this->actingAs($student)->get(route('student.points', ['sort' => 'amount', 'dir' => 'desc']))->assertOk()
            ->assertSeeInOrder(['দশ', 'তিন', 'এক']);
    }
}
