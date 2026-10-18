<?php

namespace Tests\Feature\Student;

use App\Models\AppNotification;
use App\Models\ProfileChangeRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileChangeRequestTest extends TestCase
{
    use RefreshDatabase;

    private function student(): User
    {
        return $this->makeStudent(['name' => 'আব্দুল্লাহ', 'guardian_name' => 'আব্দুর রহমান']);
    }

    private function ask(User $s, array $over = [])
    {
        return $this->actingAs($s)->post(route('student.profile.request'), array_merge([
            'name' => 'আব্দুল্লাহ আল মামুন', 'guardian_name' => 'আব্দুর রহমান', 'reason' => 'বানান ভুল ছিল',
        ], $over));
    }

    public function test_a_student_still_cannot_change_their_name_directly(): void
    {
        $s = $this->student();

        $this->actingAs($s)->put(route('student.profile.update'), ['name' => 'Hacked', 'guardian_name' => 'X', 'email' => 'a@b.co'])->assertRedirect();

        $this->assertSame('আব্দুল্লাহ', $s->fresh()->name);
        $this->assertSame('আব্দুর রহমান', $s->fresh()->guardian_name);
    }

    public function test_a_request_changes_nothing_until_approved_and_notifies_the_reviewers(): void
    {
        $admin = $this->makeAdmin();
        $s = $this->student();

        $this->ask($s)->assertRedirect()->assertSessionHas('success');

        $req = ProfileChangeRequest::sole();
        $this->assertSame('pending', $req->status);
        $this->assertSame(['আব্দুল্লাহ', 'আব্দুল্লাহ আল মামুন'], [$req->old_name, $req->new_name]);
        $this->assertSame('আব্দুল্লাহ', $s->fresh()->name, 'the account is untouched while pending');
        $this->assertDatabaseHas('app_notifications', ['user_id' => $admin->id, 'type' => 'profile_request']);
    }

    public function test_approving_applies_both_fields_audits_it_and_tells_the_student(): void
    {
        $admin = $this->makeAdmin();
        $s = $this->student();
        $this->ask($s, ['guardian_name' => 'আব্দুর রহীম']);
        $req = ProfileChangeRequest::sole();

        $this->actingAs($admin)->post(route('admin.profile-requests.approve', $req))->assertRedirect()->assertSessionHas('success');

        $s->refresh();
        $this->assertSame('আব্দুল্লাহ আল মামুন', $s->name);
        $this->assertSame('আব্দুর রহীম', $s->guardian_name);
        $this->assertSame('approved', $req->fresh()->status);
        $this->assertSame($admin->id, $req->fresh()->reviewed_by);
        $this->assertDatabaseHas('audit_logs', ['action' => 'student.profile_change_approved']);
        $this->assertDatabaseHas('app_notifications', ['user_id' => $s->id, 'type' => 'profile_request_decision']);
        $this->assertSame(0, AppNotification::where('user_id', $admin->id)->where('type', 'profile_request')->count(), 'reviewers\' pending pings are cleared');
    }

    public function test_rejecting_keeps_the_old_values_and_shows_the_note_to_the_student(): void
    {
        $admin = $this->makeAdmin();
        $s = $this->student();
        $this->ask($s);
        $req = ProfileChangeRequest::sole();

        $this->actingAs($admin)->post(route('admin.profile-requests.reject', $req), ['note' => 'নথি দেখে নিশ্চিত হইনি'])->assertRedirect();

        $this->assertSame('আব্দুল্লাহ', $s->fresh()->name);
        $this->actingAs($s)->get(route('student.profile.edit'))->assertOk()->assertSee('নথি দেখে নিশ্চিত হইনি');
    }

    public function test_a_decision_is_final_and_cannot_be_applied_twice(): void
    {
        $admin = $this->makeAdmin();
        $s = $this->student();
        $this->ask($s);
        $req = ProfileChangeRequest::sole();

        $this->actingAs($admin)->post(route('admin.profile-requests.approve', $req));
        $s->forceFill(['name' => 'অন্য নাম'])->save();   // staff corrected it again meanwhile

        $this->actingAs($admin)->post(route('admin.profile-requests.approve', $req))->assertSessionHas('error');
        $this->assertSame('অন্য নাম', $s->fresh()->name, 'a stale second approval must not overwrite newer data');
    }

    public function test_only_one_pending_request_and_no_op_requests_are_refused(): void
    {
        $s = $this->student();

        $this->ask($s, ['name' => 'আব্দুল্লাহ', 'guardian_name' => 'আব্দুর রহমান'])->assertSessionHasErrors('name');
        $this->ask($s)->assertSessionHas('success');
        $this->ask($s, ['name' => 'আরেকটি নাম'])->assertSessionHasErrors('name');

        $this->assertSame(1, ProfileChangeRequest::count());
    }

    public function test_a_student_can_withdraw_only_their_own_pending_request(): void
    {
        $s = $this->student();
        $other = $this->student();
        $this->ask($s);
        $req = ProfileChangeRequest::sole();

        $this->actingAs($other)->delete(route('student.profile.request.cancel', $req))->assertForbidden();
        $this->assertSame('pending', $req->fresh()->status);

        $this->actingAs($s)->delete(route('student.profile.request.cancel', $req))->assertRedirect();
        $this->assertSame('cancelled', $req->fresh()->status);
    }

    public function test_the_admin_screen_is_permission_gated_and_students_cannot_decide(): void
    {
        $admin = $this->makeAdmin();
        $s = $this->student();
        $this->ask($s);
        $req = ProfileChangeRequest::sole();

        $this->actingAs($admin)->get(route('admin.profile-requests.index'))->assertOk()->assertSee('আব্দুল্লাহ আল মামুন');
        $this->actingAs($s)->get(route('admin.profile-requests.index'))->assertForbidden();
        $this->actingAs($s)->post(route('admin.profile-requests.approve', $req))->assertForbidden();
        $this->assertSame('আব্দুল্লাহ', $s->fresh()->name);
    }

    public function test_names_are_nfc_normalised_and_whitespace_collapsed(): void
    {
        $s = $this->student();
        $decomposed = "জানুয়ারি  \u{09A8}ূ\u{09B0}"; // য় written as ya + nukta, plus a double space

        $this->ask($s, ['name' => $decomposed]);

        $saved = ProfileChangeRequest::sole()->new_name;
        $this->assertStringNotContainsString('  ', $saved);
        $this->assertSame(\App\Services\Import\BengaliText::normalise($saved), $saved);
    }
}
