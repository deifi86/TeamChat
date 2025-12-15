<?php

namespace App\Http\Controllers\Api;

use App\Events\ReactionAdded;
use App\Events\ReactionRemoved;
use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\DirectConversation;
use App\Models\Message;
use App\Models\MessageReaction;
use App\Services\EmojiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReactionController extends Controller
{
    /**
     * Reaktion zu einer Nachricht hinzufügen
     */
    public function store(Request $request, Message $message): JsonResponse
    {
        $user = $request->user();

        // Zugriffsprüfung
        if (!$this->canAccessMessage($user, $message)) {
            return response()->json([
                'message' => 'Message not found',
            ], 404);
        }

        $validated = $request->validate([
            'emoji' => ['required', 'string', 'max:50'],
        ]);

        // Prüfen ob bereits vorhanden
        $existing = MessageReaction::where('message_id', $message->id)
            ->where('user_id', $user->id)
            ->where('emoji', $validated['emoji'])
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'Reaction already exists',
                'reaction' => $this->formatReaction($existing),
            ]);
        }

        $reaction = MessageReaction::create([
            'message_id' => $message->id,
            'user_id' => $user->id,
            'emoji' => $validated['emoji'],
        ]);

        $reaction->refresh();

        // Event broadcasten
        broadcast(new ReactionAdded($message, $reaction, $user))->toOthers();

        return response()->json([
            'message' => 'Reaction added',
            'reaction' => $this->formatReaction($reaction),
        ], 201);
    }

    /**
     * Reaktion von einer Nachricht entfernen
     */
    public function destroy(Request $request, Message $message, string $emoji): JsonResponse
    {
        $user = $request->user();

        // Zugriffsprüfung
        if (!$this->canAccessMessage($user, $message)) {
            return response()->json([
                'message' => 'Message not found',
            ], 404);
        }

        $reaction = MessageReaction::where('message_id', $message->id)
            ->where('user_id', $user->id)
            ->where('emoji', $emoji)
            ->first();

        if (!$reaction) {
            return response()->json([
                'message' => 'Reaction not found',
            ], 404);
        }

        $reactionData = $this->formatReaction($reaction);
        $reaction->delete();

        // Event broadcasten
        broadcast(new ReactionRemoved($message, $emoji, $user))->toOthers();

        return response()->json([
            'message' => 'Reaction removed',
        ]);
    }

    /**
     * Alle Reaktionen einer Nachricht abrufen
     */
    public function index(Request $request, Message $message): JsonResponse
    {
        $user = $request->user();

        if (!$this->canAccessMessage($user, $message)) {
            return response()->json([
                'message' => 'Message not found',
            ], 404);
        }

        $reactions = $message->reactions()
            ->with('user:id,username,avatar_path')
            ->get()
            ->groupBy('emoji')
            ->map(fn ($group, $emoji) => [
                'emoji' => $emoji,
                'count' => $group->count(),
                'users' => $group->map(fn ($r) => [
                    'id' => $r->user->id,
                    'username' => $r->user->username,
                    'avatar_url' => $r->user->avatar_url,
                ])->values(),
                'has_reacted' => $group->contains('user_id', $user->id),
            ])
            ->values();

        return response()->json([
            'reactions' => $reactions,
        ]);
    }

    /**
     * Toggle Reaktion (add/remove)
     */
    public function toggle(Request $request, Message $message): JsonResponse
    {
        $user = $request->user();

        if (!$this->canAccessMessage($user, $message)) {
            return response()->json([
                'message' => 'Message not found',
            ], 404);
        }

        $validated = $request->validate([
            'emoji' => ['required', 'string', 'max:50'],
        ]);

        $existing = MessageReaction::where('message_id', $message->id)
            ->where('user_id', $user->id)
            ->where('emoji', $validated['emoji'])
            ->first();

        if ($existing) {
            $existing->delete();
            broadcast(new ReactionRemoved($message, $validated['emoji'], $user))->toOthers();

            return response()->json([
                'message' => 'Reaction removed',
                'action' => 'removed',
            ]);
        }

        $reaction = MessageReaction::create([
            'message_id' => $message->id,
            'user_id' => $user->id,
            'emoji' => $validated['emoji'],
        ]);

        $reaction->refresh();

        broadcast(new ReactionAdded($message, $reaction, $user))->toOthers();

        return response()->json([
            'message' => 'Reaction added',
            'action' => 'added',
            'reaction' => $this->formatReaction($reaction),
        ], 201);
    }

    /**
     * Prüft ob User Zugriff auf die Nachricht hat
     */
    private function canAccessMessage($user, Message $message): bool
    {
        if ($message->messageable_type === 'channel') {
            $channel = Channel::find($message->messageable_id);
            return $channel && $user->isMemberOfChannel($channel);
        }

        if ($message->messageable_type === 'direct') {
            $conversation = DirectConversation::find($message->messageable_id);
            return $conversation && $conversation->hasUser($user) && $conversation->isAccepted();
        }

        return false;
    }

    /**
     * Verfügbare Emojis abrufen
     */
    public function emojis(): JsonResponse
    {
        $emojiService = app(EmojiService::class);

        return response()->json([
            'categories' => $emojiService->getShortcodesByCategory(),
            'popular' => ['👍', '❤️', '😂', '🎉', '👏', '🔥', '✅', '🙏', '😊', '🤔'],
        ]);
    }

    private function formatReaction(MessageReaction $reaction): array
    {
        return [
            'id' => $reaction->id,
            'emoji' => $reaction->emoji,
            'user_id' => $reaction->user_id,
            'created_at' => $reaction->created_at->toIso8601String(),
        ];
    }
}
