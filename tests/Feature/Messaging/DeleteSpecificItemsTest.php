<?php

namespace Tests\Feature\Messaging;

use App\Models\AmolDayNote;
use App\Models\AppNotification;
use App\Services\Amol\AmolService;
use App\Services\Messaging\MessageService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeleteSpecificItemsTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_delete_any_single_message_and_only_that_one(): void
    {
        $student = $this->makeStudent();
        $admin = $this->makeAdmin();
        $svc = app(MessageService::class);
        $conv = $svc->threadFor($student);
        $keep = $svc->send($conv, $student, 'keep me');
        $theirs = $svc->send($conv, $student, 'delete me (student wrote it)');
        $mine = $svc->send($conv, $admin, 'delete me too (admin wrote it)');

        foreach ([$theirs, $mine] as $m) {
            $this->actingAs($admin)->deleteJson(route('admin.messages.message.destroy', ['conversation' => $conv, 'message' => $m]))
                ->assertOk()->assertJson(['deleted' => true]);
        }

        $this->assertSame([$keep->id], $conv->messages()->pluck('id')->all());
        $this->assertSame(2, \App\Models\AuditLog::where('action', 'message.deleted')->count());
        $this->assertStringNotContainsString('delete me', json_encode(\App\Models\AuditLog::all()->toArray()));
    }

    public function test_students_cannot_delete_messages_and_the_message_must_belong_to_the_thread(): void
    {
        $student = $this->makeStudent();
        $admin = $this->makeAdmin();
        $svc = app(MessageService::class);
        $conv = $svc->threadFor($student);
        $msg = $svc->send($conv, $student, 'mine');
        $other = $svc->threadFor($this->makeStudent());

        $this->actingAs($student)->deleteJson(route('admin.messages.message.destroy', ['conversation' => $conv, 'message' => $msg]))->assertForbidden();
        $this->actingAs($admin)->deleteJson(route('admin.messages.message.destroy', ['conversation' => $other, 'message' => $msg]))->assertNotFound();
        $this->assertDatabaseHas('messages', ['id' => $msg->id]);
    }

    public function test_deleting_the_newest_message_rolls_the_thread_timestamp_back(): void
    {
        $student = $this->makeStudent();
        $admin = $this->makeAdmin();
        $svc = app(MessageService::class);
        $conv = $svc->threadFor($student);
        $old = $svc->send($conv, $student, 'old');
        \Illuminate\Support\Carbon::setTestNow(now()->addHour());
        $new = $svc->send($conv, $student, 'new');
        $this->assertTrue($conv->fresh()->last_message_at->gt($old->created_at));

        $this->actingAs($admin)->deleteJson(route('admin.messages.message.destroy', ['conversation' => $conv, 'message' => $new]))->assertOk();

        $this->assertTrue($conv->fresh()->last_message_at->equalTo($old->fresh()->created_at));
        \Illuminate\Support\Carbon::setTestNow();
    }

    public function test_an_admin_can_edit_and_delete_a_day_comment_and_the_bell_entry_goes_with_it(): void
    {
        $student = $this->makeStudent();
        $admin = $this->makeAdmin();
        $date = CarbonImmutable::now()->startOfDay();
        app(AmolService::class)->saveNote($student, $date, 'প্রথম মন্তব্য', $admin);

        $this->actingAs($admin)->post(route('admin.amol.note', $student), ['date' => $date->toDateString(), 'note' => 'সংশোধিত মন্তব্য'])->assertRedirect();
        $this->assertSame('সংশোধিত মন্তব্য', AmolDayNote::sole()->note);
        $this->assertSame(1, AppNotification::where('subject_type', 'amol_day_note')->count());

        $this->actingAs($admin)->delete(route('admin.amol.note.destroy', $student), ['date' => $date->toDateString()])->assertRedirect();

        $this->assertSame(0, AmolDayNote::count());
        $this->assertSame(0, AppNotification::where('subject_type', 'amol_day_note')->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'amol.note_deleted']);
    }

    public function test_students_cannot_delete_a_comment_and_deleting_a_missing_one_is_harmless(): void
    {
        $student = $this->makeStudent();
        $admin = $this->makeAdmin();
        $date = CarbonImmutable::now()->startOfDay();
        app(AmolService::class)->saveNote($student, $date, 'x', $admin);

        $this->actingAs($student)->delete(route('admin.amol.note.destroy', $student), ['date' => $date->toDateString()])->assertForbidden();
        $this->assertSame(1, AmolDayNote::count());

        $this->actingAs($admin)->delete(route('admin.amol.note.destroy', $student), ['date' => $date->subDays(3)->toDateString()])->assertRedirect();
        $this->assertSame(1, AmolDayNote::count());
    }
}
