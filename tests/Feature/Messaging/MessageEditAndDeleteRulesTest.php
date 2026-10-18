<?php

namespace Tests\Feature\Messaging;

use App\Models\Conversation;
use App\Models\Message;
use App\Services\Messaging\MessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessageEditAndDeleteRulesTest extends TestCase
{
    use RefreshDatabase;

    private function thread(): array
    {
        $student = $this->makeStudent();
        $admin = $this->makeAdmin();
        $svc = app(MessageService::class);
        $conv = $svc->threadFor($student);

        return [$student, $admin, $conv, $svc->send($conv, $student, 'student says'), $svc->send($conv, $admin, 'admin says')];
    }

    private function url(Conversation $c, Message $m): string
    {
        return route('admin.messages.update', ['conversation' => $c, 'message' => $m]);
    }

    public function test_an_admin_can_edit_their_own_message_and_it_is_marked_edited(): void
    {
        [, $admin, $conv, , $mine] = $this->thread();

        $this->actingAs($admin)->putJson($this->url($conv, $mine), ['body' => '  corrected  '])
            ->assertOk()->assertJsonPath('message.body', 'corrected')->assertJsonPath('message.edited', true);

        $this->assertSame('corrected', $mine->fresh()->body);
        $this->assertNotNull($mine->fresh()->edited_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'message.edited']);
    }

    public function test_the_edited_text_is_never_copied_into_the_audit_log(): void
    {
        [, $admin, $conv, , $mine] = $this->thread();
        $this->actingAs($admin)->putJson($this->url($conv, $mine), ['body' => 'SECRET-NEW-TEXT'])->assertOk();

        $this->assertStringNotContainsString('SECRET-NEW-TEXT', json_encode(\App\Models\AuditLog::all()->toArray()));
    }

    public function test_an_admin_cannot_edit_a_students_message_or_a_colleagues(): void
    {
        [, $admin, $conv, $studentMsg, $mine] = $this->thread();

        $this->actingAs($admin)->putJson($this->url($conv, $studentMsg), ['body' => 'tampered'])->assertForbidden();
        $this->assertSame('student says', $studentMsg->fresh()->body);

        $colleague = $this->makeAdmin();
        $this->actingAs($colleague)->putJson($this->url($conv, $mine), ['body' => 'tampered'])->assertForbidden();
        $this->assertSame('admin says', $mine->fresh()->body);
    }

    public function test_a_student_cannot_edit_any_message_and_has_no_edit_or_delete_route(): void
    {
        [$student, , $conv, $studentMsg] = $this->thread();

        $this->actingAs($student)->putJson($this->url($conv, $studentMsg), ['body' => 'x'])->assertForbidden();

        $names = collect(app('router')->getRoutes()->getRoutes())->filter(fn ($r) => str_starts_with($r->getName() ?? '', 'student.messages'));
        $this->assertSame([], $names->filter(fn ($r) => array_intersect(['PUT', 'PATCH', 'DELETE'], $r->methods()))->map->getName()->values()->all());

        $this->actingAs($student)->delete(route('admin.messages.destroy', $conv))->assertForbidden();
        $this->assertDatabaseHas('conversations', ['id' => $conv->id]);
    }

    public function test_a_message_must_belong_to_the_thread_in_the_url_and_stay_non_empty(): void
    {
        [, $admin, $conv, , $mine] = $this->thread();
        $other = app(MessageService::class)->threadFor($this->makeStudent());

        $this->actingAs($admin)->putJson($this->url($other, $mine), ['body' => 'x'])->assertNotFound();
        $this->actingAs($admin)->putJson($this->url($conv, $mine), ['body' => '   '])->assertUnprocessable();
        $this->assertNull($mine->fresh()->edited_at);
    }

    public function test_saving_unchanged_text_does_not_mark_it_edited_and_the_ui_shows_the_edit_button_only_to_staff(): void
    {
        [$student, $admin, $conv, , $mine] = $this->thread();

        $this->actingAs($admin)->putJson($this->url($conv, $mine), ['body' => 'admin says'])->assertOk()->assertJsonPath('message.edited', false);

        $this->assertMatchesRegularExpression('/@click="startEdit\(\d+,/', $this->actingAs($admin)->get(route('admin.messages.show', $conv))->getContent());
        $studentPage = $this->actingAs($student)->get(route('student.messages.index'))->assertDontSee(__('messages.delete_thread'))->getContent();
        $this->assertDoesNotMatchRegularExpression('/@click="startEdit\(\d+,/', $studentPage);
    }
}
