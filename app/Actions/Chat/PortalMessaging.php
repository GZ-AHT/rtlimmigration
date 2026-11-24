<?php

namespace App\Actions\Chat;

use App\Enums\ChatKind;
use App\Enums\ChatStatus;
use App\Enums\ModerationStatus;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Events\ChatMessagePosted;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatModerationEvent;
use App\Models\ChatRead;
use App\Models\Student;
use App\Models\User;
use App\Notifications\ChatMessageReceived;
use App\Support\Chat\Security\InspectedAttachment;
use App\Support\Chat\Security\Verdict;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Messaging kept in this system: a student's portal thread with staff, and
 * a website visitor's (guest) chat with staff. Tawk.to offers no API for
 * sending agent replies, so these messages are never sent to Tawk.to.
 *
 * Each student has one continuous thread. It follows the student's
 * counselor: when the counselor changes, the thread is reassigned and a
 * note is added, and every earlier message and file stays in place.
 *
 * Every message carries a moderation status (see ModerationStatus). Each
 * new message, and each change of status, takes the next number in the
 * conversation's sequence (seq), which is the polling cursor.
 */
class PortalMessaging
{
    public const MAX_BODY = 5000;

    public const MAX_FILES = 5;

    public const PAGE = 50;

    /** The student's thread, created on first use. */
    public function threadFor(Student $student): ChatConversation
    {
        $student->loadMissing('user:id,name,email');

        return ChatConversation::firstOrCreate(
            ['kind' => ChatKind::Portal, 'external_id' => ChatConversation::portalKey($student->id)],
            [
                'status' => ChatStatus::Open,
                'student_id' => $student->id,
                'assigned_to' => $student->counselor_id,
                'visitor_name' => $student->user->name,
                'visitor_email' => $student->user->email,
                'started_at' => now(),
            ],
        );
    }

    public function findByClientId(ChatConversation $conversation, string $clientId): ?ChatMessage
    {
        return $conversation->chatMessages()->where('client_id', $clientId)->with(['attachments', 'user:id,name'])->first();
    }

    /**
     * Saves a message, once: a retry with the same client ID returns the
     * message already stored instead of adding a second one. Files have
     * already passed AttachmentInspector; they are stored under random
     * names with the extension read from their contents.
     *
     * @param  list<InspectedAttachment>  $files
     */
    public function post(ChatConversation $conversation, ?User $sender, string $senderType, ?string $body, array $files = [], ?string $clientId = null, ?Verdict $verdict = null): ChatMessage
    {
        if ($clientId !== null && ($existing = $this->findByClientId($conversation, $clientId))) {
            return $existing;
        }

        $body = $body !== null && trim($body) !== '' ? trim($body) : null;
        $verdict ??= Verdict::approved();
        $disk = (string) config('documents.disk');
        $stored = [];

        foreach ($files as $file) {
            $path = $file->file->storeAs("chat-attachments/{$conversation->id}", Str::random(40).'.'.$file->extension, $disk);

            if ($path === false) {
                $this->discard($stored);

                throw new \RuntimeException('Could not store a chat attachment.');
            }

            $stored[] = [
                'disk' => $disk,
                'path' => $path,
                'original_name' => $file->displayName,
                'mime_type' => $file->mimeType,
                'size' => $file->size,
            ];
        }

        try {
            [$message, $previousLastId] = DB::transaction(function () use ($conversation, $sender, $senderType, $body, $stored, $clientId, $verdict) {
                // Serialises writers on this thread so IDs, counts and the last-message pointer agree.
                $locked = ChatConversation::query()->lockForUpdate()->findOrFail($conversation->id);
                $previousLastId = (int) $locked->last_message_id;

                $message = $locked->chatMessages()->create([
                    'seq' => ++$locked->last_seq,
                    'user_id' => $sender?->id,
                    'sender_type' => $senderType,
                    'body' => $body,
                    'client_id' => $clientId,
                    'moderation_status' => $verdict->status,
                    'moderation_reasons' => $verdict->reasons ?: null,
                ]);

                foreach ($stored as $attachment) {
                    $message->attachments()->create($attachment);
                }

                if ($verdict->status !== ModerationStatus::Approved) {
                    ChatModerationEvent::query()->create([
                        'chat_message_id' => $message->id,
                        'chat_conversation_id' => $locked->id,
                        'from_status' => null,
                        'to_status' => $verdict->status,
                        'reasons' => $verdict->reasons,
                        'created_at' => now(),
                    ]);
                }

                $locked->last_message_id = $message->id;
                $locked->last_message_at = $message->created_at;
                $locked->message_count++;

                if (! $verdict->status->isHeld()) {
                    $locked->first_message ??= $body;
                }

                // A student or visitor writing again reopens a resolved thread.
                if (in_array($senderType, ChatMessage::MODERATED_SENDERS, true)) {
                    $locked->status = ChatStatus::Open;
                }

                $locked->save();
                $conversation->setRawAttributes($locked->getAttributes(), true);

                if ($sender !== null) {
                    $this->markRead($conversation, $sender, $message->id);
                }

                return [$message, $previousLastId];
            });
        } catch (UniqueConstraintViolationException $e) {
            // The same message was saved by a parallel retry.
            $this->discard($stored);

            return $clientId !== null && ($existing = $this->findByClientId($conversation, $clientId)) ? $existing : throw $e;
        } catch (\Throwable $e) {
            $this->discard($stored);

            throw $e;
        }

        $message->load(['attachments', 'user:id,name']);

        // Held messages reach no one until a moderator approves them.
        if (! $message->isHeld()) {
            $this->notify($conversation, $message, $previousLastId);
        }

        ChatMessagePosted::dispatch($message);

        return $message;
    }

    /**
     * Gives a message the next sequence number after its moderation
     * changed, so every open thread picks up the new state on its next poll.
     */
    public function resequence(ChatMessage $message): void
    {
        DB::transaction(function () use ($message) {
            $locked = ChatConversation::query()->lockForUpdate()->findOrFail($message->chat_conversation_id);
            $message->seq = ++$locked->last_seq;
            $message->save();
            $locked->save();
        });
    }

    /** Tells the other side about a message a moderator has just released. */
    public function released(ChatMessage $message): void
    {
        $conversation = $message->conversation;
        $message->loadMissing(['attachments', 'user:id,name']);

        // Everything before it counts as already notified.
        $this->notify($conversation, $message, 0);
        ChatMessagePosted::dispatch($message);
    }

    /**
     * An automatic reply. Stored once per $clientId (a retry returns the first one), and it
     * notifies no one, reopens nothing and is not moderated: it is our own text.
     */
    public function automated(ChatConversation $conversation, string $body, string $clientId): ChatMessage
    {
        if ($existing = $this->findByClientId($conversation, $clientId)) {
            return $existing;
        }

        try {
            $message = DB::transaction(function () use ($conversation, $body, $clientId) {
                $locked = ChatConversation::query()->lockForUpdate()->findOrFail($conversation->id);
                $message = $locked->chatMessages()->create(['seq' => ++$locked->last_seq, 'sender_type' => ChatMessage::BOT, 'body' => $body, 'client_id' => $clientId]);
                $locked->forceFill(['last_message_id' => $message->id, 'last_message_at' => $message->created_at, 'message_count' => $locked->message_count + 1])->save();
                $conversation->setRawAttributes($locked->getAttributes(), true);

                return $message;
            });
        } catch (UniqueConstraintViolationException $e) {
            return $this->findByClientId($conversation, $clientId) ?? throw $e;
        }

        $message->load(['attachments', 'user:id,name']);
        ChatMessagePosted::dispatch($message);

        return $message;
    }

    /** A note from the system, such as a reassignment. Does not notify anyone. */
    public function note(ChatConversation $conversation, string $text): ChatMessage
    {
        return DB::transaction(function () use ($conversation, $text) {
            $locked = ChatConversation::query()->lockForUpdate()->findOrFail($conversation->id);
            $message = $locked->chatMessages()->create(['seq' => ++$locked->last_seq, 'sender_type' => ChatMessage::SYSTEM, 'body' => $text]);
            $locked->forceFill(['last_message_id' => $message->id, 'last_message_at' => $message->created_at])->save();
            $conversation->setRawAttributes($locked->getAttributes(), true);

            return $message;
        });
    }

    /**
     * Moves the student's thread to their new counselor (called from
     * AssignStudentCounselor, inside its transaction). History stays.
     */
    public function counselorChanged(Student $student, ?User $from, ?User $to): void
    {
        $thread = ChatConversation::query()
            ->where('kind', ChatKind::Portal)
            ->where('external_id', ChatConversation::portalKey($student->id))
            ->first();

        if ($thread === null || $thread->assigned_to === $to?->id) {
            return;
        }

        $thread->assigned_to = $to?->id;
        $thread->save();

        $this->note($thread, match (true) {
            $to === null => __('Conversation unassigned from :from.', ['from' => $from->name ?? __('the previous counselor')]),
            $from === null => __('Conversation assigned to :to.', ['to' => $to->name]),
            default => __('Conversation moved from :from to :to.', ['from' => $from->name, 'to' => $to->name]),
        });
    }

    /** Records that the user has read up to a message (default: the latest). */
    public function markRead(ChatConversation $conversation, User $user, ?int $upTo = null): void
    {
        $upTo ??= (int) $conversation->last_message_id;

        if ($upTo === 0) {
            return;
        }

        // Two portable statements: create the row if missing, then only move it forward.
        ChatRead::query()->insertOrIgnore([
            'chat_conversation_id' => $conversation->id, 'user_id' => $user->id,
            'last_read_message_id' => $upTo, 'created_at' => now(), 'updated_at' => now(),
        ]);

        ChatRead::query()
            ->where('chat_conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->where('last_read_message_id', '<', $upTo)
            ->update(['last_read_message_id' => $upTo, 'updated_at' => now()]);
    }

    /**
     * Messages changed after a sequence number (polling: new messages and
     * moderation changes), or the page before a message ID (loading
     * earlier history), oldest first.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, ChatMessage>
     */
    public function messages(ChatConversation $conversation, ?int $afterSeq = null, ?int $before = null)
    {
        $query = $conversation->chatMessages()->with(['attachments', 'user:id,name']);

        if ($afterSeq !== null) {
            return $query->where('seq', '>', $afterSeq)->orderBy('id')->limit(200)->get();
        }

        return $query
            ->when($before !== null, fn ($q) => $q->where('id', '<', $before))
            ->orderByDesc('id')->limit(self::PAGE)->get()->reverse()->values();
    }

    /**
     * Notifies the other side, but only for the first message they have not
     * read yet, so a burst of messages is one notification.
     */
    private function notify(ChatConversation $conversation, ChatMessage $message, int $previousLastId): void
    {
        $conversation->loadMissing('student.user', 'assignee');

        // Staff replies to a website visitor reach them on the chat page;
        // only a visitor who linked a student account gets a notification.
        $recipients = in_array($message->sender_type, ChatMessage::MODERATED_SENDERS, true)
            ? ($conversation->assignee && $conversation->assignee->isActive() ? collect([$conversation->assignee]) : $this->chatManagers())
            : collect(array_filter([$conversation->student?->user]));

        $recipients = $recipients->filter(function (User $user) use ($conversation, $message, $previousLastId) {
            if ($message->user_id !== null && $user->id === $message->user_id) {
                return false;
            }

            $read = (int) ChatRead::query()->where('chat_conversation_id', $conversation->id)->where('user_id', $user->id)->value('last_read_message_id');

            return $read >= $previousLastId || $previousLastId === 0;
        });

        Notification::send($recipients, new ChatMessageReceived($message));
    }

    /** @return Collection<int, User> */
    private function chatManagers()
    {
        return User::query()
            ->where('status', UserStatus::Active->value)
            ->where(fn (Builder $q) => $q->permission('chat.view-all')->orWhereHas('roles', fn (Builder $r) => $r->where('name', Role::SuperAdmin->value)))
            ->get();
    }

    /** @param  list<array{disk: string, path: string}>  $stored */
    private function discard(array $stored): void
    {
        foreach ($stored as $file) {
            Storage::disk($file['disk'])->delete($file['path']);
        }
    }
}
