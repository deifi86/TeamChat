<?php

namespace App\Http\Controllers\Api;

use App\Events\MessageDeleted;
use App\Events\MessageEdited;
use App\Events\NewMessage;
use App\Events\UserTyping;
use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\Message;
use App\Services\MessageEncryptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function __construct(
        private MessageEncryptionService $encryptionService
    ) {}

    /**
     * GET /api/channels/{channel}/messages
     * Lädt Nachrichten eines Channels mit Pagination
     */
    public function channelMessages(Request $request, Channel $channel): JsonResponse
    {
        $user = $request->user();

        if (!$user->isMemberOfChannel($channel)) {
            return response()->json([
                'message' => 'You are not a member of this channel',
            ], 403);
        }

        $validated = $request->validate([
            'before' => ['nullable', 'integer', 'exists:messages,id'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $limit = $validated['limit'] ?? 50;

        $query = $channel->messages()
            ->with(['sender:id,username,avatar_path,status', 'reactions'])
            ->withTrashed(false)
            ->orderBy('created_at', 'desc');

        if (isset($validated['before'])) {
            $beforeMessage = Message::find($validated['before']);
            if ($beforeMessage) {
                $query->where('created_at', '<', $beforeMessage->created_at);
            }
        } else {
            // Default: Nachrichten der letzten 3 Tage
            $query->where('created_at', '>=', now()->subDays(3));
        }

        $messages = $query->limit($limit + 1)->get();

        $hasMore = $messages->count() > $limit;
        if ($hasMore) {
            $messages = $messages->take($limit);
        }

        $formattedMessages = $messages->map(fn ($message) => $this->formatMessage($message));

        return response()->json([
            'messages' => $formattedMessages->reverse()->values(),
            'has_more' => $hasMore,
        ]);
    }

    /**
     * POST /api/channels/{channel}/messages
     * Sendet eine neue Nachricht in einem Channel
     */
    public function storeChannelMessage(Request $request, Channel $channel): JsonResponse
    {
        $user = $request->user();

        if (!$user->isMemberOfChannel($channel)) {
            return response()->json([
                'message' => 'You are not a member of this channel',
            ], 403);
        }

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:10000'],
            'parent_id' => ['nullable', 'exists:messages,id'],
        ]);

        // Parent-Message muss zum gleichen Channel gehören
        if (isset($validated['parent_id'])) {
            $parentMessage = Message::find($validated['parent_id']);
            if ($parentMessage->messageable_type !== 'channel' ||
                $parentMessage->messageable_id !== $channel->id) {
                return response()->json([
                    'message' => 'Parent message does not belong to this channel',
                ], 422);
            }
        }

        // Verschlüsseln
        $encrypted = $this->encryptionService->encryptForStorage($validated['content']);

        $message = Message::create([
            'messageable_type' => 'channel',
            'messageable_id' => $channel->id,
            'sender_id' => $user->id,
            'content' => $encrypted['content'],
            'content_iv' => $encrypted['content_iv'],
            'content_type' => 'text',
            'parent_id' => $validated['parent_id'] ?? null,
        ]);

        $message->load('sender:id,username,avatar_path,status');

        // Event broadcasten
        broadcast(new NewMessage($message, $validated['content']))->toOthers();

        return response()->json([
            'message' => 'Message sent',
            'data' => $this->formatMessage($message),
        ], 201);
    }

    /**
     * PUT /api/messages/{message}
     * Bearbeitet eine Nachricht (nur eigene, innerhalb 24h)
     */
    public function update(Request $request, Message $message): JsonResponse
    {
        $user = $request->user();

        if ($message->sender_id !== $user->id) {
            return response()->json([
                'message' => 'You can only edit your own messages',
            ], 403);
        }

        // Zeitlimit: 24 Stunden
        if ($message->created_at->diffInHours(now()) > 24) {
            return response()->json([
                'message' => 'Messages can only be edited within 24 hours',
            ], 422);
        }

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:10000'],
        ]);

        // Verschlüsseln
        $encrypted = $this->encryptionService->encryptForStorage($validated['content']);

        $message->update([
            'content' => $encrypted['content'],
            'content_iv' => $encrypted['content_iv'],
            'edited_at' => now(),
        ]);

        // Event broadcasten
        broadcast(new MessageEdited($message, $validated['content']))->toOthers();

        return response()->json([
            'message' => 'Message updated',
            'data' => $this->formatMessage($message->fresh()->load('sender', 'reactions')),
        ]);
    }

    /**
     * DELETE /api/messages/{message}
     * Löscht eine Nachricht (nur eigene oder als Admin)
     */
    public function destroy(Request $request, Message $message): JsonResponse
    {
        $user = $request->user();

        // Prüfen ob eigene Nachricht oder Admin
        $isOwner = $message->sender_id === $user->id;
        $isAdmin = false;

        if ($message->messageable_type === 'channel') {
            $channel = Channel::find($message->messageable_id);
            $isAdmin = $channel && $user->isAdminOf($channel->company);
        }

        if (!$isOwner && !$isAdmin) {
            return response()->json([
                'message' => 'You can only delete your own messages',
            ], 403);
        }

        // Event vorbereiten (vor dem Löschen)
        $event = new MessageDeleted($message);

        // Soft Delete
        $message->delete();

        // Event broadcasten
        broadcast($event)->toOthers();

        return response()->json([
            'message' => 'Message deleted',
        ]);
    }

    /**
     * POST /api/channels/{channel}/typing
     * Sendet Typing-Indicator
     */
    public function typing(Request $request, Channel $channel): JsonResponse
    {
        $user = $request->user();

        if (!$user->isMemberOfChannel($channel)) {
            return response()->json([
                'message' => 'You are not a member of this channel',
            ], 403);
        }

        broadcast(new UserTyping($user, 'channel', $channel->id))->toOthers();

        return response()->json([
            'message' => 'Typing indicator sent',
        ]);
    }

    /**
     * Formatiert eine Message für die API-Response
     */
    private function formatMessage(Message $message): array
    {
        $decryptedContent = $this->encryptionService->decryptFromStorage(
            $message->content,
            $message->content_iv
        );

        return [
            'id' => $message->id,
            'content' => $decryptedContent,
            'content_type' => $message->content_type,
            'sender' => [
                'id' => $message->sender->id,
                'username' => $message->sender->username,
                'avatar_url' => $message->sender->avatar_url,
                'status' => $message->sender->status,
            ],
            'reactions' => $this->aggregateReactions($message),
            'parent_id' => $message->parent_id,
            'edited_at' => $message->edited_at?->toIso8601String(),
            'created_at' => $message->created_at->toIso8601String(),
        ];
    }

    /**
     * Aggregiert Reactions für eine Message
     */
    private function aggregateReactions(Message $message): array
    {
        return $message->reactions
            ->groupBy('emoji')
            ->map(fn ($group, $emoji) => [
                'emoji' => $emoji,
                'count' => $group->count(),
                'user_ids' => $group->pluck('user_id')->toArray(),
            ])
            ->values()
            ->toArray();
    }
}
