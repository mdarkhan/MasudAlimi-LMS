<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Some legacy exams were marked by hand and published as a signed result sheet (PDF). That sheet's
 * order is the institution's FINAL order — it breaks equal scores by submission serial, which the
 * imported rows cannot reproduce (their recorded times are all the same). So a legacy attempt can
 * carry its position on that sheet, and LeaderboardService follows it for that quiz.
 *
 * `special_consideration` marks an attempt the examiner allowed under special consideration: it is
 * still shown with its real score, but it takes no rank and is always listed last.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->unsignedSmallInteger('sheet_position')->nullable()->after('submission_seq');
            $table->boolean('special_consideration')->default(false)->after('sheet_position');
        });
    }

    public function down(): void
    {
        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->dropColumn(['sheet_position', 'special_consideration']);
        });
    }
};
