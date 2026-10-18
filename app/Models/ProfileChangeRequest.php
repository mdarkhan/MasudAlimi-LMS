<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One student's request to correct their name / guardian name. Written only by ProfileChangeService. */
#[Fillable(['user_id', 'old_name', 'old_guardian_name', 'new_name', 'new_guardian_name', 'reason', 'status', 'reviewed_by', 'reviewed_at', 'review_note'])]
class ProfileChangeRequest extends Model
{
    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    public const CANCELLED = 'cancelled';

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    /** @param Builder<ProfileChangeRequest> $query */
    public function scopePending(Builder $query): void
    {
        $query->where('status', self::PENDING);
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }

    public function changesName(): bool
    {
        return $this->new_name !== $this->old_name;
    }

    public function changesGuardian(): bool
    {
        return ($this->new_guardian_name ?? '') !== ($this->old_guardian_name ?? '');
    }

    /** @return BelongsTo<User, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
