<?php

namespace App\Services\Profile;

use App\Models\ProfileChangeRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Import\BengaliText;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The only writer of profile_change_requests, and the only path by which a STUDENT's name
 * or guardian name can change on their own initiative: they ask, staff holding
 * students.update decide. Identity is what the leaderboards, printed answer sheets and
 * certificates rely on, so it is never self-editable (see ProfileController::update).
 */
class ProfileChangeService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly NotificationService $notifications,
    ) {}

    public function pendingFor(User $student): ?ProfileChangeRequest
    {
        return ProfileChangeRequest::query()->where('user_id', $student->getKey())->pending()->latest('id')->first();
    }

    /** The most recent decided (approved/rejected) request — shown to the student as feedback. */
    public function lastDecisionFor(User $student): ?ProfileChangeRequest
    {
        return ProfileChangeRequest::query()->where('user_id', $student->getKey())
            ->whereIn('status', [ProfileChangeRequest::APPROVED, ProfileChangeRequest::REJECTED])
            ->latest('reviewed_at')->latest('id')->first();
    }

    /** @throws ValidationException */
    public function submit(User $student, string $name, ?string $guardian, string $reason): ProfileChangeRequest
    {
        $name = $this->clean($name);
        $guardian = $this->clean((string) $guardian) ?: null;

        if ($name === ($student->name ?? '') && $guardian === (($student->guardian_name ?? '') ?: null)) {
            throw ValidationException::withMessages(['name' => __('profile_requests.nothing_changed')]);
        }

        $request = DB::transaction(function () use ($student, $name, $guardian, $reason) {
            // Serialise per student so a double-click cannot create two pending requests.
            User::query()->whereKey($student->getKey())->lockForUpdate()->first();

            if ($this->pendingFor($student) !== null) {
                throw ValidationException::withMessages(['name' => __('profile_requests.already_pending')]);
            }

            return ProfileChangeRequest::query()->create([
                'user_id' => $student->getKey(),
                'old_name' => $student->name,
                'old_guardian_name' => $student->guardian_name,
                'new_name' => $name,
                'new_guardian_name' => $guardian,
                'reason' => trim($reason),
            ]);
        });

        foreach ($this->reviewers() as $admin) {
            $this->notifications->notify(
                $admin,
                'profile_request',
                $student->name.' নাম/তথ্য সংশোধনের অনুরোধ করেছেন',
                trim($reason),
                route('admin.profile-requests.index'),
                'profile_change_request',
                (int) $request->getKey(),
            );
        }

        return $request;
    }

    /** The student withdraws their own pending request. */
    public function cancel(ProfileChangeRequest $request, User $student): void
    {
        abort_unless((int) $request->user_id === (int) $student->getKey(), 403);

        if ($request->isPending()) {
            $request->forceFill(['status' => ProfileChangeRequest::CANCELLED])->save();
            $this->notifications->forgetSubject('profile_change_request', (int) $request->getKey());
        }
    }

    /** Apply the requested values to the student. Returns false if it was already decided. */
    public function approve(ProfileChangeRequest $request, User $admin, ?string $note = null): bool
    {
        $applied = DB::transaction(function () use ($request, $admin, $note) {
            $fresh = ProfileChangeRequest::query()->lockForUpdate()->find($request->getKey());
            if ($fresh === null || ! $fresh->isPending()) {
                return false;
            }

            $student = User::query()->lockForUpdate()->findOrFail($fresh->user_id);
            $before = $student->only(['name', 'guardian_name']);
            $student->forceFill(['name' => $fresh->new_name, 'guardian_name' => $fresh->new_guardian_name])->save();

            $fresh->forceFill([
                'status' => ProfileChangeRequest::APPROVED,
                'reviewed_by' => $admin->getKey(),
                'reviewed_at' => now(),
                'review_note' => $this->noteOrNull($note),
            ])->save();

            $this->audit->log('student.profile_change_approved', $student, $before, $student->only(['name', 'guardian_name']), $admin);

            return true;
        });

        if ($applied) {
            $this->tellStudent($request->fresh(), 'আপনার তথ্য সংশোধনের অনুরোধ অনুমোদিত হয়েছে');
        }

        return $applied;
    }

    public function reject(ProfileChangeRequest $request, User $admin, ?string $note = null): bool
    {
        $done = DB::transaction(function () use ($request, $admin, $note) {
            $fresh = ProfileChangeRequest::query()->lockForUpdate()->find($request->getKey());
            if ($fresh === null || ! $fresh->isPending()) {
                return false;
            }

            $fresh->forceFill([
                'status' => ProfileChangeRequest::REJECTED,
                'reviewed_by' => $admin->getKey(),
                'reviewed_at' => now(),
                'review_note' => $this->noteOrNull($note),
            ])->save();

            $this->audit->log('student.profile_change_rejected', $fresh->student, null, ['request_id' => $fresh->getKey()], $admin);

            return true;
        });

        if ($done) {
            $this->tellStudent($request->fresh(), 'আপনার তথ্য সংশোধনের অনুরোধ গ্রহণ করা হয়নি');
        }

        return $done;
    }

    public function pendingCount(): int
    {
        return ProfileChangeRequest::query()->pending()->count();
    }

    private function tellStudent(ProfileChangeRequest $request, string $title): void
    {
        $this->notifications->forgetSubject('profile_change_request', (int) $request->getKey());   // reviewers' copies are done
        $this->notifications->notify(
            $request->student,
            'profile_request_decision',
            $title,
            $request->review_note,
            route('student.profile.edit'),
            'profile_change_decision',
            (int) $request->getKey(),
        );
    }

    /** Staff who may act on the request (students.update; super_admin included via hasPermission). */
    private function reviewers()
    {
        return User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', [Role::SUPER_ADMIN, Role::ADMIN]))
            ->get()
            ->filter(fn (User $u) => $u->hasPermission('students.update'));
    }

    /** NFC, trimmed, single-spaced — so the same name always compares equal. */
    private function clean(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', BengaliText::normalise($value)));
    }

    private function noteOrNull(?string $note): ?string
    {
        $note = trim((string) $note);

        return $note === '' ? null : $note;
    }
}
