<?php

namespace App\Http\Controllers\Api;

use App\Events\ConversationAccepted;
use App\Events\NewConversationRequest;
use App\Http\Controllers\Controller;
use App\Models\DirectConversation;
use App\Models\Message;
use App\Models\User;
use App\Services\MessageEncryptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DirectConversationController extends Controller
{
    public function __construct(
        private MessageEncryptionService $encryptionService
    ) {}

    /**
     * Liste aller Direct Conversations des Users
     * GET /api/conversations
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $conversations = DirectConversation::where('user_one_id', $user->id)
            ->orWhere('user_two_id', $user->id)
            ->with(['userOne:id,username,avatar_path,status', 'userTwo:id,username,avatar_path,status'])
            ->withCount(['messages as unread_count' => function ($query) use ($user) {
                $query->where('sender_id', '!=', $user->id)
                    ->whereDoesntHave('readReceipts', function ($q) use ($user) {
                        $q->where('user_id', $user->id);
                    });
            }])
            ->get()
            ->map(fn ($conv) => $this->formatConversation($conv, $user))
            ->sortByDesc('updated_at')
            ->values();

        return response()->json(['conversations' => $conversations]);
    }

    /**
     * Neue Conversation starten (Chat-Anfrage)
     * POST /api/conversations
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id', 'different:' . $user->id],
        ]);

        $otherUser = User::find($validated['user_id']);

        // Prüfen ob bereits existiert
        $userOneId = min($user->id, $otherUser->id);
        $userTwoId = max($user->id, $otherUser->id);

        $existing = DirectConversation::where('user_one_id', $userOneId)
            ->where('user_two_id', $userTwoId)
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'Conversation already exists',
                'conversation' => $this->formatConversation($existing, $user),
            ]);
        }

        $conversation = DirectConversation::findOrCreateBetween($user, $otherUser);

        // Event broadcasten
        broadcast(new NewConversationRequest($conversation, $user, $otherUser));

        return response()->json([
            'message' => 'Conversation request sent',
            'conversation' => $this->formatConversation($conversation, $user),
        ], 201);
    }

    /**
     * Conversation-Details abrufen
     * GET /api/conversations/{conversation}
     */
    public function show(Request $request, DirectConversation $conversation): JsonResponse
    {
        $user = $request->user();

        if (!$conversation->hasUser($user)) {
            return response()->json([
                'message' => 'Conversation not found',
            ], 404);
        }

        return response()->json([
            'conversation' => $this->formatConversation($conversation, $user),
        ]);
    }

    /**
     * Chat-Anfrage akzeptieren
     * POST /api/conversations/{conversation}/accept
     */
    public function accept(Request $request, DirectConversation $conversation): JsonResponse
    {
        $user = $request->user();

        if (!$conversation->hasUser($user)) {
            return response()->json([
                'message' => 'Conversation not found',
            ], 404);
        }

        if ($conversation->isAccepted()) {
            return response()->json([
                'message' => 'Conversation is already accepted',
            ], 422);
        }

        $conversation->acceptBy($user);

        // Event broadcasten
        broadcast(new ConversationAccepted($conversation->fresh(), $user));

        return response()->json([
            'message' => 'Conversation accepted',
            'conversation' => $this->formatConversation($conversation->fresh(), $user),
        ]);
    }

    /**
     * Chat-Anfrage ablehnen (löscht die Conversation)
     * POST /api/conversations/{conversation}/reject
     */
    public function reject(Request $request, DirectConversation $conversation): JsonResponse
    {
        $user = $request->user();

        if (!$conversation->hasUser($user)) {
            return response()->json([
                'message' => 'Conversation not found',
            ], 404);
        }

        if ($conversation->isAccepted()) {
            return response()->json([
                'message' => 'Cannot reject an accepted conversation',
            ], 422);
        }

        $conversation->delete();

        return response()->json([
            'message' => 'Conversation rejected',
        ]);
    }

    /**
     * Conversation verlassen/löschen
     * DELETE /api/conversations/{conversation}
     */
    public function destroy(Request $request, DirectConversation $conversation): JsonResponse
    {
        $user = $request->user();

        if (!$conversation->hasUser($user)) {
            return response()->json([
                'message' => 'Conversation not found',
            ], 404);
        }

        $conversation->delete();

        return response()->json([
            'message' => 'Conversation deleted',
        ]);
    }

    /**
     * Nachrichten einer Conversation laden
     * GET /api/conversations/{conversation}/messages
     */
    public function messages(Request $request, DirectConversation $conversation): JsonResponse
    {
        $user = $request->user();

        if (!$conversation->hasUser($user)) {
            return response()->json([
                'message' => 'Conversation not found',
            ], 404);
        }

        if (!$conversation->isAccepted()) {
            return response()->json([
                'message' => 'Conversation not yet accepted',
            ], 403);
        }

        $validated = $request->validate([
            'before' => ['nullable', 'integer', 'exists:messages,id'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $limit = $validated['limit'] ?? 50;

        $query = $conversation->messages()
            ->with(['sender:id,username,avatar_path,status', 'reactions'])
            ->orderBy('created_at', 'desc');

        if (isset($validated['before'])) {
            $beforeMessage = Message::find($validated['before']);
            if ($beforeMessage) {
                $query->where('created_at', '<', $beforeMessage->created_at);
            }
        } else {
            // Default: Letzte 3 Tage
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
     * Nachricht in Conversation senden
     * POST /api/conversations/{conversation}/messages
     */
    public function sendMessage(Request $request, DirectConversation $conversation): JsonResponse
    {
        $user = $request->user();

        if (!$conversation->hasUser($user)) {
            return response()->json([
                'message' => 'Conversation not found',
            ], 404);
        }

        if (!$conversation->isAccepted()) {
            return response()->json([
                'message' => 'Conversation not yet accepted',
            ], 403);
        }

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:10000'],
            'parent_id' => ['nullable', 'exists:messages,id'],
        ]);

        // Parent-Validierung
        if (isset($validated['parent_id'])) {
            $parentMessage = Message::find($validated['parent_id']);
            if ($parentMessage->messageable_type !== 'direct' ||
                $parentMessage->messageable_id !== $conversation->id) {
                return response()->json([
                    'message' => 'Parent message does not belong to this conversation',
                ], 422);
            }
        }

        // Verschlüsseln
        $encrypted = $this->encryptionService->encryptForStorage($validated['content']);

        $message = Message::create([
            'messageable_type' => 'direct',
            'messageable_id' => $conversation->id,
            'sender_id' => $user->id,
            'content' => $encrypted['content'],
            'content_iv' => $encrypted['content_iv'],
            'content_type' => 'text',
            'parent_id' => $validated['parent_id'] ?? null,
        ]);

        $message->load('sender:id,username,avatar_path,status');

        // TODO: Event broadcasten
        // broadcast(new NewMessage($message, $validated['content']))->toOthers();

        return response()->json([
            'message' => 'Message sent',
            'data' => $this->formatMessage($message),
        ], 201);
    }

    /**
     * Typing-Indicator für Conversation senden
     * POST /api/conversations/{conversation}/typing
     */
    public function typing(Request $request, DirectConversation $conversation): JsonResponse
    {
        $user = $request->user();

        if (!$conversation->hasUser($user)) {
            return response()->json([
                'message' => 'Conversation not found',
            ], 404);
        }

        if (!$conversation->isAccepted()) {
            return response()->json([
                'message' => 'Conversation not yet accepted',
            ], 403);
        }

        // TODO: Event broadcasten
        // broadcast(new UserTyping($user, 'direct', $conversation->id))->toOthers();

        return response()->json([
            'message' => 'Typing indicator sent',
        ]);
    }

    /**
     * Format Conversation für Response
     */
    private function formatConversation(DirectConversation $conversation, User $user): array
    {
        $otherUser = $conversation->getOtherUser($user);
        $isUserOne = $conversation->user_one_id === $user->id;

        // Letzte Nachricht laden
        $lastMessage = $conversation->messages()
            ->with('sender:id')
            ->latest()
            ->first();

        $lastMessageData = null;
        if ($lastMessage) {
            $decryptedContent = $this->encryptionService->decryptFromStorage(
                $lastMessage->content,
                $lastMessage->content_iv
            );

            $lastMessageData = [
                'content' => mb_substr($decryptedContent, 0, 100) . (mb_strlen($decryptedContent) > 100 ? '...' : ''),
                'created_at' => $lastMessage->created_at->toIso8601String(),
                'is_mine' => $lastMessage->sender_id === $user->id,
            ];
        }

        return [
            'id' => $conversation->id,
            'other_user' => [
                'id' => $otherUser->id,
                'username' => $otherUser->username,
                'avatar_url' => $otherUser->avatar_url,
                'status' => $otherUser->status,
            ],
            'is_accepted' => $conversation->isAccepted(),
            'is_pending_my_acceptance' => $isUserOne
                ? !$conversation->user_one_accepted
                : !$conversation->user_two_accepted,
            'last_message' => $lastMessageData,
            'unread_count' => $conversation->unread_count ?? 0,
            'updated_at' => $conversation->updated_at->toIso8601String(),
        ];
    }

    /**
     * Format Message für Response
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
            ],
            'reactions' => $this->aggregateReactions($message),
            'parent_id' => $message->parent_id,
            'edited_at' => $message->edited_at?->toIso8601String(),
            'created_at' => $message->created_at->toIso8601String(),
        ];
    }

    /**
     * Aggregiere Reactions für eine Message
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
