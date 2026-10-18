<?php

namespace App\Support;

use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Shapes messages for the chat client. A strict allow-list, like the exam presenters:
 * id, body, author name, timestamp and which side wrote it — never a sender's email,
 * phone, roll or any other account field (SECURITY.md §2.13).
 *
 * `body` travels as plain text and MUST be rendered with x-text / {{ }}. It is never
 * HTML or Markdown: both sides of the thread are untrusted authors.
 */
class MessagePresenter
{
    /**
     * @param  Collection<int, Message>  $messages
     * @return array<int, array{id:int, body:string, mine:bool, author:string, at:string}>
     */
    public static function collection(Collection $messages, User $viewer): array
    {
        return $messages->map(fn (Message $message) => self::one($message, $viewer))->values()->all();
    }

    /** "Today" / "Yesterday" / the date — the chat's day separators, as WhatsApp shows them. */
    private static function dayLabel(Message $message): string
    {
        $d = $message->created_at;
        if (! $d) {
            return '';
        }

        return match (true) {
            $d->isToday() => __('messages.today'),
            $d->isYesterday() => __('messages.yesterday'),
            default => bn($d->format('d/m/Y')),
        };
    }

    /**
     * @return array{id:int, body:string, mine:bool, author:string, at:string, day:string, day_label:string, time:string, read:bool}
     */
    public static function one(Message $message, User $viewer): array
    {
        return [
            'id' => (int) $message->getKey(),
            'body' => $message->body,
            'mine' => (int) $message->sender_id === (int) $viewer->getKey(),
            'author' => $message->sender?->name ?? '—',
            'at' => $message->created_at?->format('d/m/Y H:i') ?? '',
            'day' => $message->created_at?->format('Y-m-d') ?? '',
            'day_label' => self::dayLabel($message),
            'time' => $message->created_at ? bn($message->created_at->format('h:i')).' '.($message->created_at->format('A') === 'AM' ? __('messages.am') : __('messages.pm')) : '',
            'read' => $message->read_at !== null,
            'edited' => $message->edited_at !== null,
        ];
    }
}
