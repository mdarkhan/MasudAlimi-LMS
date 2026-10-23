<?php

namespace App\Services\Quiz;

use App\Models\AppNotification;
use App\Models\PointTransaction;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Points\InsufficientPointsException;
use App\Services\Points\PointService;
use Illuminate\Support\Facades\DB;

/**
 * Removes ANY quiz together with everything that hangs off it — its questions, every attempt (official and
 * practice) with its answers, adjustments and regrade entries, its bonus grants and bell notices — when
 * ordinary deletion is refused because official attempts exist (QuizController::destroy protects exam
 * history). The owner asked for this deliberately; it is super-admin only and needs the title typed back.
 *
 * Totals and ranks need no separate fix-up: LeaderboardService derives every per-quiz, course and overall
 * board live from the remaining attempts, so a student's total drops by exactly this quiz's marks and the
 * boards re-rank on the next page load. Course-topper bonuses ALREADY paid are not recalculated.
 *
 * The point ledger stays append-only: the entry fee each attempt cost is RETURNED with a refund row, and
 * any reward bonus the quiz paid out is taken back with an adjustment row (skipped, and reported, if the
 * student has already spent it). Ledger rows are never edited or deleted. One audit entry records what
 * was removed; the answers themselves are not copied anywhere.
 */
class QuizPurgeService
{
    public function __construct(
        private readonly PointService $points,
        private readonly AuditLogger $audit,
    ) {}

    /** @return array{attempts: int, refunded: int, bonus_reversed: int, bonus_skipped: int} */
    public function purge(Quiz $quiz, User $by): array
    {
        return DB::transaction(function () use ($quiz, $by) {
            $stats = ['attempts' => 0, 'refunded' => 0, 'bonus_reversed' => 0, 'bonus_skipped' => 0];
            $reason = 'পরীক্ষা মুছে ফেলা: '.$quiz->title;

            $attempts = QuizAttempt::query()->where('quiz_id', $quiz->getKey())->with('user')->lockForUpdate()->get();
            foreach ($attempts as $attempt) {
                $fee = $attempt->point_transaction_id ? PointTransaction::query()->find($attempt->point_transaction_id) : null;
                if ($fee && $fee->amount < 0) {
                    $this->points->credit($attempt->user, -$fee->amount, PointTransaction::TYPE_REFUND, $reason, $by);
                    $stats['refunded'] += -$fee->amount;
                }
            }

            foreach (DB::table('reward_grants')->where('quiz_id', $quiz->getKey())->get() as $grant) {
                $student = User::query()->find($grant->user_id);
                if ($student && $grant->points > 0) {
                    try {
                        $this->points->debit($student, (int) $grant->points, PointTransaction::TYPE_ADJUSTMENT, $reason, $by);
                        $stats['bonus_reversed'] += (int) $grant->points;
                    } catch (InsufficientPointsException) {
                        $stats['bonus_skipped'] += (int) $grant->points;
                    }
                }
            }
            DB::table('reward_grants')->where('quiz_id', $quiz->getKey())->delete();

            // the exam's bell notifications (exam_soon / exam_closing / results) point at a page that is going away
            AppNotification::query()->whereIn('subject_type', ['exam_soon', 'exam_closing', 'results'])
                ->where('subject_id', $quiz->getKey())->delete();

            $stats['attempts'] = $attempts->count();
            QuizAttempt::query()->where('quiz_id', $quiz->getKey())->delete();   // answers, adjustments, regrade entries cascade

            $this->audit->log('quiz.purged', $quiz, actor: $by, before: [
                'title' => $quiz->title,
                'slug' => $quiz->slug,
                'attempts' => $stats['attempts'],
                'points_refunded' => $stats['refunded'],
                'bonus_reversed' => $stats['bonus_reversed'],
                'bonus_skipped' => $stats['bonus_skipped'],
            ]);

            $quiz->delete();   // questions/options, regrade runs, notification deliveries cascade

            return $stats;
        });
    }
}
