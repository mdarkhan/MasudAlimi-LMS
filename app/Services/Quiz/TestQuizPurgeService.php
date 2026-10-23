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
 * Removes a quiz that was only ever a TEST — together with every attempt made on it — when ordinary
 * deletion is refused because official attempts exist (QuizController::destroy protects real exam
 * history). Super-admin only, and only after the title is typed back as confirmation.
 *
 * The point ledger stays append-only: the entry fee each attempt cost is RETURNED with a refund row, and
 * any reward bonus the quiz paid out is taken back with an adjustment row (skipped, and reported, if the
 * student has already spent it). Ledger rows are never edited or deleted. One audit entry records what
 * was removed; the answers themselves are not copied anywhere.
 */
class TestQuizPurgeService
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
            $reason = 'টেস্ট পরীক্ষা মুছে ফেলা: '.$quiz->title;

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
