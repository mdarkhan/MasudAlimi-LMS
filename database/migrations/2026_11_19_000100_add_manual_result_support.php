<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Exams that are not taken online — an oral exam, a hand-marked written one — still count toward the
 * course and overall standings. Staff enter the marks; there are no questions.
 *
 *  - quizzes.manual_result: "result-only exam". total_marks is set by staff (not derived from questions),
 *    so the integrity checks and recalculateTotalMarks() must leave it alone.
 *  - quiz_attempts.score_breakdown: the parts of the mark, e.g. [{"label":"এটিচিউড","score":19,"max":20},
 *    {"label":"বই","score":80,"max":80}] — shown on the result page, never used for ranking.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            $table->boolean('manual_result')->default(false)->after('counts_toward_overall');
        });
        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->json('score_breakdown')->nullable()->after('special_consideration');
        });
    }

    public function down(): void
    {
        Schema::table('quiz_attempts', fn (Blueprint $t) => $t->dropColumn('score_breakdown'));
        Schema::table('quizzes', fn (Blueprint $t) => $t->dropColumn('manual_result'));
    }
};
