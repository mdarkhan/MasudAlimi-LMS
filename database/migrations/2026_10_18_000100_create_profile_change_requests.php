<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A student asking for their name / guardian's name to be corrected. Identity fields stay
 * admin-authoritative: nothing changes until staff approve. The old values are snapshotted so
 * the decision screen (and the audit trail) show exactly what would change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('old_name', 150);
            $table->string('old_guardian_name', 150)->nullable();
            $table->string('new_name', 150);
            $table->string('new_guardian_name', 150)->nullable();
            $table->text('reason');
            $table->string('status', 12)->default('pending');   // pending | approved | rejected | cancelled
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_change_requests');
    }
};
