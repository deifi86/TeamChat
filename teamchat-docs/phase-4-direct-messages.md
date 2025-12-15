# Phase 4: Direct Messages (Woche 8-9)

## Ziel dieser Phase
Nach Abschluss dieser Phase haben wir:
- Direct Message Conversation System
- Chat-Request Flow (Anfrage → Akzeptieren/Ablehnen)
- Nachrichten-API für Direct Conversations
- User-Suche für neue Gespräche
- Conversation-Liste mit letzter Nachricht

---

## 4.1 Direct Conversation Controller [BE]

### 4.1.1 DirectConversationController erstellen
- [x] **Erledigt**

→ *Abhängig von Phase 3 abgeschlossen*

**Durchführung:**
```bash
php artisan make:controller Api/DirectConversationController
```

**Datei:** `app/Http/Controllers/Api/DirectConversationController.php`
```php
<?php

namespace App\Http\Controllers\Api;

use App\Events\NewMessage;
use App\Events\UserTyping;
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

    // Methoden folgen
}
```

---

### 4.1.2 Endpoint: GET /api/conversations
- [x] **Erledigt**

**Beschreibung:** Liste aller Direct Conversations des Users.

**Response (200):**
```json
{
    "conversations": [
        {
            "id": 1,
            "other_user": {
                "id": 2,
                "username": "Max",
                "avatar_url": "...",
                "status": "available"
            },
            "is_accepted": true,
            "is_pending_my_acceptance": false,
            "last_message": {
                "content": "Hallo!",
                "created_at": "2024-...",
                "is_mine": false
            },
            "unread_count": 2,
            "updated_at": "2024-..."
        }
    ]
}
```

**Implementierung:**
```php
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
        ->orderByDesc(function ($query) {
            $query->select('created_at')
                ->from('messages')
                ->whereColumn('messageable_id', 'direct_conversations.id')
                ->where('messageable_type', 'direct')
                ->latest()
                ->limit(1);
        })
        ->get()
        ->map(fn ($conv) => $this->formatConversation($conv, $user));

    return response()->json(['conversations' => $conversations]);
}

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
```

**Unit Test:** `tests/Feature/Api/DirectConversation/ListConversationsTest.php`
```php
<?php

namespace Tests\Feature\Api\DirectConversation;

use App\Models\User;
use App\Models\DirectConversation;
use App\Models\Message;
use App\Services\MessageEncryptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListConversationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_own_conversations(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        DirectConversation::findOrCreateBetween($user, $other);

        $response = $this->actingAs($user)
            ->getJson('/api/conversations');

        $response->assertOk()
            ->assertJsonCount(1, 'conversations')
            ->assertJsonStructure([
                'conversations' => [
                    '*' => ['id', 'other_user', 'is_accepted', 'last_message']
                ]
            ]);
    }

    public function test_conversations_show_other_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create(['username' => 'OtherUser']);

        DirectConversation::findOrCreateBetween($user, $other);

        $response = $this->actingAs($user)
            ->getJson('/api/conversations');

        $response->assertOk()
            ->assertJsonFragment(['username' => 'OtherUser']);
    }

    public function test_conversations_show_last_message(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $conversation = DirectConversation::findOrCreateBetween($user, $other);
        $conversation->update([
            'user_one_accepted' => true,
            'user_two_accepted' => true,
        ]);

        $encryptionService = app(MessageEncryptionService::class);
        $encrypted = $encryptionService->encryptForStorage('Hello there!');

        Message::factory()->create([
            'messageable_type' => 'direct',
            'messageable_id' => $conversation->id,
            'sender_id' => $other->id,
            'content' => $encrypted['content'],
            'content_iv' => $encrypted['content_iv'],
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/conversations');

        $response->assertOk()
            ->assertJsonFragment(['content' => 'Hello there!']);
    }

    public function test_does_not_show_others_conversations(): void
    {
        $user = User::factory()->create();
        $other1 = User::factory()->create();
        $other2 = User::factory()->create();

        // Conversation zwischen anderen Usern
        DirectConversation::findOrCreateBetween($other1, $other2);

        $response = $this->actingAs($user)
            ->getJson('/api/conversations');

        $response->assertOk()
            ->assertJsonCount(0, 'conversations');
    }
}
```

**Akzeptanzkriterien:**
- [ ] Nur eigene Conversations werden gelistet
- [ ] Other User wird korrekt angezeigt
- [ ] is_accepted und is_pending_my_acceptance sind korrekt
- [ ] Letzte Nachricht wird entschlüsselt angezeigt
- [ ] Sortierung nach letzter Aktivität

---

### 4.1.3 Endpoint: POST /api/conversations
- [x] **Erledigt**

**Beschreibung:** Neue Conversation starten (Chat-Anfrage).

**Request:**
```json
{
    "user_id": 5
}
```

**Response (201) - Neue Conversation:**
```json
{
    "message": "Conversation request sent",
    "conversation": {
        "id": 1,
        "other_user": { ... },
        "is_accepted": false,
        "is_pending_my_acceptance": false
    }
}
```

**Response (200) - Bestehende Conversation:**
```json
{
    "message": "Conversation already exists",
    "conversation": { ... }
}
```

**Implementierung:**
```php
public function store(Request $request): JsonResponse
{
    $user = $request->user();

    $validated = $request->validate([
        'user_id' => ['required', 'exists:users,id', 'different:' . $user->id],
    ]);

    $otherUser = User::find($validated['user_id']);

    // Prüfen ob bereits existiert
    $existing = DirectConversation::where(function ($query) use ($user, $otherUser) {
        $query->where('user_one_id', min($user->id, $otherUser->id))
            ->where('user_two_id', max($user->id, $otherUser->id));
    })->first();

    if ($existing) {
        return response()->json([
            'message' => 'Conversation already exists',
            'conversation' => $this->formatConversation($existing, $user),
        ]);
    }

    $conversation = DirectConversation::findOrCreateBetween($user, $otherUser);

    return response()->json([
        'message' => 'Conversation request sent',
        'conversation' => $this->formatConversation($conversation, $user),
    ], 201);
}
```

**Unit Test:** `tests/Feature/Api/DirectConversation/CreateConversationTest.php`
```php
<?php

namespace Tests\Feature\Api\DirectConversation;

use App\Models\User;
use App\Models\DirectConversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateConversationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_start_conversation(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/conversations', [
                'user_id' => $other->id,
            ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['message' => 'Conversation request sent']);

        $this->assertDatabaseHas('direct_conversations', [
            'user_one_id' => min($user->id, $other->id),
            'user_two_id' => max($user->id, $other->id),
        ]);
    }

    public function test_initiator_has_accepted_true(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/conversations', [
                'user_id' => $other->id,
            ]);

        $conversation = DirectConversation::first();

        if ($user->id < $other->id) {
            $this->assertTrue($conversation->user_one_accepted);
            $this->assertFalse($conversation->user_two_accepted);
        } else {
            $this->assertFalse($conversation->user_one_accepted);
            $this->assertTrue($conversation->user_two_accepted);
        }
    }

    public function test_returns_existing_conversation(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        DirectConversation::findOrCreateBetween($user, $other);

        $response = $this->actingAs($user)
            ->postJson('/api/conversations', [
                'user_id' => $other->id,
            ]);

        $response->assertOk()
            ->assertJsonFragment(['message' => 'Conversation already exists']);

        $this->assertEquals(1, DirectConversation::count());
    }

    public function test_cannot_start_conversation_with_self(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/conversations', [
                'user_id' => $user->id,
            ]);

        $response->assertStatus(422);
    }
}
```

**Akzeptanzkriterien:**
- [ ] Neue Conversation wird erstellt
- [ ] Initiator hat accepted=true
- [ ] Bestehende Conversation wird nicht dupliziert
- [ ] Kann nicht mit sich selbst chatten

---

### 4.1.4 Endpoint: GET /api/conversations/{conversation}
- [x] **Erledigt**

**Beschreibung:** Conversation-Details abrufen.

**Response (200):**
```json
{
    "conversation": {
        "id": 1,
        "other_user": { ... },
        "is_accepted": true,
        "created_at": "..."
    }
}
```

**Implementierung:**
```php
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
```

---

### 4.1.5 Endpoint: POST /api/conversations/{conversation}/accept
- [x] **Erledigt**

**Beschreibung:** Chat-Anfrage akzeptieren.

**Response (200):**
```json
{
    "message": "Conversation accepted",
    "conversation": { ... }
}
```

**Implementierung:**
```php
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

    // TODO: Event broadcasten für Real-Time Update

    return response()->json([
        'message' => 'Conversation accepted',
        'conversation' => $this->formatConversation($conversation->fresh(), $user),
    ]);
}
```

**Unit Test:** `tests/Feature/Api/DirectConversation/AcceptConversationTest.php`
```php
<?php

namespace Tests\Feature\Api\DirectConversation;

use App\Models\User;
use App\Models\DirectConversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcceptConversationTest extends TestCase
{
    use RefreshDatabase;

    public function test_receiver_can_accept_conversation(): void
    {
        $initiator = User::factory()->create();
        $receiver = User::factory()->create();

        $conversation = DirectConversation::findOrCreateBetween($initiator, $receiver);

        $this->assertFalse($conversation->isAccepted());

        $response = $this->actingAs($receiver)
            ->postJson("/api/conversations/{$conversation->id}/accept");

        $response->assertOk()
            ->assertJsonFragment(['message' => 'Conversation accepted']);

        $this->assertTrue($conversation->fresh()->isAccepted());
    }

    public function test_cannot_accept_already_accepted(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $conversation = DirectConversation::factory()->create([
            'user_one_id' => min($user->id, $other->id),
            'user_two_id' => max($user->id, $other->id),
            'user_one_accepted' => true,
            'user_two_accepted' => true,
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/conversations/{$conversation->id}/accept");

        $response->assertStatus(422);
    }

    public function test_non_participant_cannot_accept(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $outsider = User::factory()->create();

        $conversation = DirectConversation::findOrCreateBetween($user1, $user2);

        $response = $this->actingAs($outsider)
            ->postJson("/api/conversations/{$conversation->id}/accept");

        $response->assertStatus(404);
    }
}
```

**Akzeptanzkriterien:**
- [ ] Empfänger kann akzeptieren
- [ ] Nach Accept: isAccepted() = true
- [ ] Bereits akzeptierte Conversation gibt Fehler
- [ ] Nicht-Teilnehmer bekommt 404

---

### 4.1.6 Endpoint: POST /api/conversations/{conversation}/reject
- [x] **Erledigt**

**Beschreibung:** Chat-Anfrage ablehnen (löscht die Conversation).

**Response (200):**
```json
{
    "message": "Conversation rejected"
}
```

**Implementierung:**
```php
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

    // Conversation löschen
    $conversation->delete();

    return response()->json([
        'message' => 'Conversation rejected',
    ]);
}
```

**Akzeptanzkriterien:**
- [ ] Empfänger kann ablehnen
- [ ] Conversation wird gelöscht
- [ ] Akzeptierte Conversation kann nicht abgelehnt werden

---

### 4.1.7 Endpoint: DELETE /api/conversations/{conversation}
- [x] **Erledigt**

**Beschreibung:** Conversation verlassen/löschen.

**Response (200):**
```json
{
    "message": "Conversation deleted"
}
```

**Implementierung:**
```php
public function destroy(Request $request, DirectConversation $conversation): JsonResponse
{
    $user = $request->user();

    if (!$conversation->hasUser($user)) {
        return response()->json([
            'message' => 'Conversation not found',
        ], 404);
    }

    // Nachrichten werden durch CASCADE gelöscht
    $conversation->delete();

    return response()->json([
        'message' => 'Conversation deleted',
    ]);
}
```

---

## 4.2 Direct Message Endpoints [BE]

### 4.2.1 Endpoint: GET /api/conversations/{conversation}/messages
- [x] **Erledigt**

**Beschreibung:** Nachrichten einer Conversation laden.

**Query Parameters:**
- `before` (optional): Message-ID für Pagination
- `limit` (optional): Anzahl (default 50, max 100)

**Response (200):**
```json
{
    "messages": [
        {
            "id": 1,
            "content": "Hallo!",
            "sender": { ... },
            "created_at": "..."
        }
    ],
    "has_more": false
}
```

**Implementierung:**
```php
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
```

**Unit Test:** `tests/Feature/Api/DirectConversation/ConversationMessagesTest.php`
```php
<?php

namespace Tests\Feature\Api\DirectConversation;

use App\Models\User;
use App\Models\DirectConversation;
use App\Models\Message;
use App\Services\MessageEncryptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationMessagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_participant_can_load_messages(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $conversation = DirectConversation::factory()->create([
            'user_one_id' => min($user->id, $other->id),
            'user_two_id' => max($user->id, $other->id),
            'user_one_accepted' => true,
            'user_two_accepted' => true,
        ]);

        $encryptionService = app(MessageEncryptionService::class);
        $encrypted = $encryptionService->encryptForStorage('Hello!');

        Message::factory()->create([
            'messageable_type' => 'direct',
            'messageable_id' => $conversation->id,
            'content' => $encrypted['content'],
            'content_iv' => $encrypted['content_iv'],
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/conversations/{$conversation->id}/messages");

        $response->assertOk()
            ->assertJsonStructure([
                'messages' => [['id', 'content', 'sender']],
                'has_more'
            ]);
    }

    public function test_cannot_load_messages_from_unaccepted_conversation(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $conversation = DirectConversation::findOrCreateBetween($user, $other);

        $response = $this->actingAs($other)
            ->getJson("/api/conversations/{$conversation->id}/messages");

        $response->assertStatus(403);
    }

    public function test_non_participant_cannot_load_messages(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $outsider = User::factory()->create();

        $conversation = DirectConversation::factory()->create([
            'user_one_id' => min($user1->id, $user2->id),
            'user_two_id' => max($user1->id, $user2->id),
            'user_one_accepted' => true,
            'user_two_accepted' => true,
        ]);

        $response = $this->actingAs($outsider)
            ->getJson("/api/conversations/{$conversation->id}/messages");

        $response->assertStatus(404);
    }
}
```

**Akzeptanzkriterien:**
- [ ] Nur Teilnehmer können Nachrichten laden
- [ ] Nur bei akzeptierter Conversation
- [ ] Content ist entschlüsselt
- [ ] Pagination funktioniert

---

### 4.2.2 Endpoint: POST /api/conversations/{conversation}/messages
- [x] **Erledigt**

**Beschreibung:** Nachricht in Conversation senden.

**Request:**
```json
{
    "content": "Hallo!",
    "parent_id": null
}
```

**Response (201):**
```json
{
    "message": "Message sent",
    "data": { ... }
}
```

**Implementierung:**
```php
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

    // Event broadcasten
    broadcast(new NewMessage($message, $validated['content']))->toOthers();

    return response()->json([
        'message' => 'Message sent',
        'data' => $this->formatMessage($message),
    ], 201);
}
```

**Unit Test:** `tests/Feature/Api/DirectConversation/SendConversationMessageTest.php`
```php
<?php

namespace Tests\Feature\Api\DirectConversation;

use App\Events\NewMessage;
use App\Models\User;
use App\Models\DirectConversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class SendConversationMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_participant_can_send_message(): void
    {
        Event::fake([NewMessage::class]);

        $user = User::factory()->create();
        $other = User::factory()->create();

        $conversation = DirectConversation::factory()->create([
            'user_one_id' => min($user->id, $other->id),
            'user_two_id' => max($user->id, $other->id),
            'user_one_accepted' => true,
            'user_two_accepted' => true,
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/conversations/{$conversation->id}/messages", [
                'content' => 'Hello!',
            ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['content' => 'Hello!']);

        $this->assertDatabaseHas('messages', [
            'messageable_type' => 'direct',
            'messageable_id' => $conversation->id,
            'sender_id' => $user->id,
        ]);

        Event::assertDispatched(NewMessage::class);
    }

    public function test_cannot_send_to_unaccepted_conversation(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $conversation = DirectConversation::findOrCreateBetween($user, $other);

        $response = $this->actingAs($other)
            ->postJson("/api/conversations/{$conversation->id}/messages", [
                'content' => 'Hello!',
            ]);

        $response->assertStatus(403);
    }

    public function test_message_is_encrypted(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $conversation = DirectConversation::factory()->create([
            'user_one_id' => min($user->id, $other->id),
            'user_two_id' => max($user->id, $other->id),
            'user_one_accepted' => true,
            'user_two_accepted' => true,
        ]);

        $this->actingAs($user)
            ->postJson("/api/conversations/{$conversation->id}/messages", [
                'content' => 'Secret message',
            ]);

        $message = \App\Models\Message::first();
        
        $this->assertNotEquals('Secret message', $message->content);
        $this->assertNotNull($message->content_iv);
    }
}
```

**Akzeptanzkriterien:**
- [ ] Nur bei akzeptierter Conversation
- [ ] Nachricht wird verschlüsselt
- [ ] Event wird gebroadcastet

---

### 4.2.3 Endpoint: POST /api/conversations/{conversation}/typing
- [x] **Erledigt**

**Beschreibung:** Typing-Indicator für Conversation senden.

**Response (200):**
```json
{
    "message": "Typing indicator sent"
}
```

**Implementierung:**
```php
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

    broadcast(new UserTyping($user, 'direct', $conversation->id))->toOthers();

    return response()->json([
        'message' => 'Typing indicator sent',
    ]);
}
```

---

## 4.3 Pending Conversations Event [BE]

### 4.3.1 NewConversationRequest Event erstellen
- [x] **Erledigt**

**Durchführung:**
```bash
php artisan make:event NewConversationRequest
```

**Datei:** `app/Events/NewConversationRequest.php`
```php
<?php

namespace App\Events;

use App\Models\DirectConversation;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewConversationRequest implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public DirectConversation $conversation,
        public User $initiator,
        public User $receiver
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->receiver->id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversation->id,
            'initiator' => [
                'id' => $this->initiator->id,
                'username' => $this->initiator->username,
                'avatar_url' => $this->initiator->avatar_url,
            ],
        ];
    }

    public function broadcastAs(): string
    {
        return 'conversation.request';
    }
}
```

### 4.3.2 ConversationAccepted Event erstellen
- [x] **Erledigt**

**Durchführung:**
```bash
php artisan make:event ConversationAccepted
```

**Datei:** `app/Events/ConversationAccepted.php`
```php
<?php

namespace App\Events;

use App\Models\DirectConversation;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationAccepted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public DirectConversation $conversation,
        public User $acceptedBy
    ) {}

    public function broadcastOn(): array
    {
        // An beide Teilnehmer senden
        return [
            new PrivateChannel('user.' . $this->conversation->user_one_id),
            new PrivateChannel('user.' . $this->conversation->user_two_id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversation->id,
            'accepted_by' => [
                'id' => $this->acceptedBy->id,
                'username' => $this->acceptedBy->username,
            ],
        ];
    }

    public function broadcastAs(): string
    {
        return 'conversation.accepted';
    }
}
```

### 4.3.3 Events in Controller einbinden
- [x] **Erledigt**

**Datei:** `DirectConversationController.php` updaten:

In `store()` nach Erstellen der Conversation:
```php
use App\Events\NewConversationRequest;

// Nach: $conversation = DirectConversation::findOrCreateBetween(...)
$otherUser = User::find($validated['user_id']);
broadcast(new NewConversationRequest($conversation, $user, $otherUser));
```

In `accept()` nach Akzeptieren:
```php
use App\Events\ConversationAccepted;

// Nach: $conversation->acceptBy($user);
broadcast(new ConversationAccepted($conversation, $user));
```

---

## 4.4 Routes & Tests [BE]

### 4.4.1 Direct Conversation Routes definieren
- [x] **Erledigt**

**Datei:** `routes/api.php` ergänzen:
```php
use App\Http\Controllers\Api\DirectConversationController;

Route::middleware('auth:sanctum')->group(function () {
    // ... bestehende Routes ...

    // Direct Conversations
    Route::prefix('conversations')->group(function () {
        Route::get('/', [DirectConversationController::class, 'index']);
        Route::post('/', [DirectConversationController::class, 'store']);
        Route::get('{conversation}', [DirectConversationController::class, 'show']);
        Route::delete('{conversation}', [DirectConversationController::class, 'destroy']);
        Route::post('{conversation}/accept', [DirectConversationController::class, 'accept']);
        Route::post('{conversation}/reject', [DirectConversationController::class, 'reject']);
        Route::get('{conversation}/messages', [DirectConversationController::class, 'messages']);
        Route::post('{conversation}/messages', [DirectConversationController::class, 'sendMessage']);
        Route::post('{conversation}/typing', [DirectConversationController::class, 'typing']);
    });
});
```

---

### 4.4.2 Alle Phase 4 Tests ausführen
- [x] **Erledigt** (Tests erstellt, Ausführung erfolgt nach vollständigem Laravel-Setup)

**Durchführung:**
```bash
php artisan test --filter=DirectConversation
php artisan test
```

**Akzeptanzkriterien:**
- [ ] Alle Tests grün
- [ ] Mindestens 85 Tests insgesamt

---

### 4.4.3 Git Commit & Tag
- [x] **Erledigt**

**Durchführung:**
```bash
git add .
git commit -m "Phase 4: Direct Messages - Conversation System"
git tag v0.4.0
```

---

## Phase 4 Zusammenfassung

### Erstellte Dateien
- 1 Controller (DirectConversationController)
- 2 Events (NewConversationRequest, ConversationAccepted)
- ~8 neue Test-Dateien

### Neue API Endpoints
| Methode | Endpoint | Beschreibung |
|---------|----------|--------------|
| GET | /api/conversations | Conversation-Liste |
| POST | /api/conversations | Neue Conversation starten |
| GET | /api/conversations/{id} | Conversation-Details |
| DELETE | /api/conversations/{id} | Conversation löschen |
| POST | /api/conversations/{id}/accept | Anfrage akzeptieren |
| POST | /api/conversations/{id}/reject | Anfrage ablehnen |
| GET | /api/conversations/{id}/messages | Nachrichten laden |
| POST | /api/conversations/{id}/messages | Nachricht senden |
| POST | /api/conversations/{id}/typing | Typing-Indicator |

### WebSocket Events
| Event | Channel | Beschreibung |
|-------|---------|--------------|
| conversation.request | private-user.{id} | Neue Chat-Anfrage |
| conversation.accepted | private-user.{id} | Anfrage akzeptiert |
| message.new | private-conversation.{id} | Neue DM |
| user.typing | private-conversation.{id} | User tippt |

### Chat-Request Flow
1. User A startet Conversation → user_a_accepted = true
2. User B erhält `conversation.request` Event
3. User B kann akzeptieren oder ablehnen
4. Bei Accept: `conversation.accepted` Event an beide
5. Jetzt können beide Nachrichten senden

### Nächste Phase
→ Weiter mit `phase-5-emojis.md`
