# Phase 3: Real-Time Chat (Woche 5-7)

## Ziel dieser Phase
Nach Abschluss dieser Phase haben wir:
- Laravel Reverb WebSocket-Server läuft
- AES-256 Verschlüsselung für Nachrichten
- CRUD-Endpoints für Nachrichten
- Real-Time Broadcasting von Nachrichten
- Typing-Indicator Events

---

## 3.1 WebSocket Setup [INFRA]

### 3.1.1 Laravel Reverb konfigurieren
- [ ] **Erledigt**

→ *Abhängig von Phase 2 abgeschlossen*

**Datei:** `.env` ergänzen:
```env
REVERB_APP_ID=teamchat
REVERB_APP_KEY=teamchat-key
REVERB_APP_SECRET=teamchat-secret
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http

BROADCAST_CONNECTION=reverb
```

**Verifizierung:**
```bash
php artisan reverb:start
# In anderem Terminal:
php artisan tinker
>>> event(new \App\Events\TestEvent());
```

**Akzeptanzkriterien:**
- [ ] Reverb startet ohne Fehler auf Port 8080
- [ ] Broadcasting Config zeigt reverb als Connection

---

### 3.1.2 Broadcasting Auth Routes konfigurieren
- [ ] **Erledigt**

**Datei:** `routes/channels.php`
```php
<?php

use App\Models\Channel;
use App\Models\DirectConversation;
use App\Models\Company;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
*/

// Private Channel für User-spezifische Events (Benachrichtigungen, Status)
Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

// Private Channel für Channel-Chat
Broadcast::channel('channel.{channelId}', function ($user, $channelId) {
    $channel = Channel::find($channelId);
    if (!$channel) {
        return false;
    }
    return $user->isMemberOfChannel($channel);
});

// Private Channel für Direct Conversation
Broadcast::channel('conversation.{conversationId}', function ($user, $conversationId) {
    $conversation = DirectConversation::find($conversationId);
    if (!$conversation) {
        return false;
    }
    return $conversation->hasUser($user);
});

// Presence Channel für Online-Status in einer Firma
Broadcast::channel('company.{companyId}', function ($user, $companyId) {
    $company = Company::find($companyId);
    if (!$company || !$user->isMemberOf($company)) {
        return false;
    }
    
    return [
        'id' => $user->id,
        'username' => $user->username,
        'avatar_url' => $user->avatar_url,
        'status' => $user->status,
    ];
});
```

**Unit Test:** `tests/Feature/Broadcasting/ChannelAuthTest.php`
```php
<?php

namespace Tests\Feature\Broadcasting;

use App\Models\User;
use App\Models\Channel;
use App\Models\Company;
use App\Models\DirectConversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChannelAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_subscribe_to_own_user_channel(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->post('/broadcasting/auth', [
                'channel_name' => 'private-user.' . $user->id,
            ]);

        $response->assertOk();
    }

    public function test_user_cannot_subscribe_to_other_user_channel(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $response = $this->actingAs($user)
            ->post('/broadcasting/auth', [
                'channel_name' => 'private-user.' . $other->id,
            ]);

        $response->assertStatus(403);
    }

    public function test_member_can_subscribe_to_channel(): void
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->create();
        $channel->members()->attach($user->id);

        $response = $this->actingAs($user)
            ->post('/broadcasting/auth', [
                'channel_name' => 'private-channel.' . $channel->id,
            ]);

        $response->assertOk();
    }

    public function test_non_member_cannot_subscribe_to_channel(): void
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->create();

        $response = $this->actingAs($user)
            ->post('/broadcasting/auth', [
                'channel_name' => 'private-channel.' . $channel->id,
            ]);

        $response->assertStatus(403);
    }

    public function test_participant_can_subscribe_to_conversation(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $conversation = DirectConversation::findOrCreateBetween($userA, $userB);

        $response = $this->actingAs($userA)
            ->post('/broadcasting/auth', [
                'channel_name' => 'private-conversation.' . $conversation->id,
            ]);

        $response->assertOk();
    }

    public function test_non_participant_cannot_subscribe_to_conversation(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $userC = User::factory()->create();
        $conversation = DirectConversation::findOrCreateBetween($userA, $userB);

        $response = $this->actingAs($userC)
            ->post('/broadcasting/auth', [
                'channel_name' => 'private-conversation.' . $conversation->id,
            ]);

        $response->assertStatus(403);
    }

    public function test_company_member_can_subscribe_to_presence_channel(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $company->members()->attach($user->id);

        $response = $this->actingAs($user)
            ->post('/broadcasting/auth', [
                'channel_name' => 'presence-company.' . $company->id,
            ]);

        $response->assertOk();
    }
}
```

**Akzeptanzkriterien:**
- [ ] User kann nur eigenen user.{id} Channel subscriben
- [ ] User kann nur Channels subscriben in denen er Mitglied ist
- [ ] User kann nur eigene Conversations subscriben
- [ ] Presence Channel gibt User-Info zurück

---

## 3.2 Encryption Service [BE]

### 3.2.1 MessageEncryptionService erstellen
- [ ] **Erledigt**

**Datei:** `app/Services/MessageEncryptionService.php`
```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class MessageEncryptionService
{
    private string $key;
    private string $cipher = 'AES-256-CBC';

    public function __construct()
    {
        $key = config('app.cipher_key') ?? config('app.key');
        
        if (str_starts_with($key, 'base64:')) {
            $key = base64_decode(substr($key, 7));
        }
        
        $this->key = $key;
    }

    /**
     * Verschlüsselt einen Text
     */
    public function encrypt(string $plaintext): array
    {
        $ivLength = openssl_cipher_iv_length($this->cipher);
        $iv = random_bytes($ivLength);
        
        $encrypted = openssl_encrypt(
            $plaintext,
            $this->cipher,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($encrypted === false) {
            Log::error('Encryption failed');
            throw new \RuntimeException('Encryption failed');
        }

        return [
            'encrypted' => base64_encode($encrypted),
            'iv' => base64_encode($iv),
        ];
    }

    /**
     * Entschlüsselt einen Text
     */
    public function decrypt(string $encryptedBase64, string $ivBase64): string
    {
        $encrypted = base64_decode($encryptedBase64);
        $iv = base64_decode($ivBase64);

        $decrypted = openssl_decrypt(
            $encrypted,
            $this->cipher,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($decrypted === false) {
            Log::error('Decryption failed');
            throw new \RuntimeException('Decryption failed');
        }

        return $decrypted;
    }

    /**
     * Verschlüsselt für DB-Speicherung und gibt formatierte Daten zurück
     */
    public function encryptForStorage(string $content): array
    {
        $result = $this->encrypt($content);
        
        return [
            'content' => $result['encrypted'],
            'content_iv' => $result['iv'],
        ];
    }

    /**
     * Entschlüsselt aus DB und gibt Klartext zurück
     */
    public function decryptFromStorage(string $content, ?string $contentIv): string
    {
        if (empty($contentIv)) {
            // Unverschlüsselte Nachricht (Legacy oder Fehler)
            return $content;
        }

        return $this->decrypt($content, $contentIv);
    }
}
```

---

### 3.2.2 Service registrieren
- [ ] **Erledigt**

**Datei:** `app/Providers/AppServiceProvider.php` ergänzen:
```php
use App\Services\MessageEncryptionService;

public function register(): void
{
    $this->app->singleton(ImageCompressionService::class);
    $this->app->singleton(MessageEncryptionService::class);
}
```

**Optional in `.env`:**
```env
# Separater Key für Nachrichtenverschlüsselung (optional)
# APP_CIPHER_KEY=base64:...
```

**Datei:** `config/app.php` ergänzen (am Ende des Arrays):
```php
'cipher_key' => env('APP_CIPHER_KEY'),
```

**Unit Test:** `tests/Unit/Services/MessageEncryptionServiceTest.php`
```php
<?php

namespace Tests\Unit\Services;

use App\Services\MessageEncryptionService;
use Tests\TestCase;

class MessageEncryptionServiceTest extends TestCase
{
    private MessageEncryptionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(MessageEncryptionService::class);
    }

    public function test_encrypt_decrypt_returns_original(): void
    {
        $original = 'Hello, World!';

        $encrypted = $this->service->encrypt($original);
        $decrypted = $this->service->decrypt($encrypted['encrypted'], $encrypted['iv']);

        $this->assertEquals($original, $decrypted);
    }

    public function test_same_text_produces_different_encrypted_output(): void
    {
        $text = 'Hello';

        $encrypted1 = $this->service->encrypt($text);
        $encrypted2 = $this->service->encrypt($text);

        $this->assertNotEquals($encrypted1['encrypted'], $encrypted2['encrypted']);
        $this->assertNotEquals($encrypted1['iv'], $encrypted2['iv']);
    }

    public function test_encrypts_unicode_correctly(): void
    {
        $original = '👋 Hallo! Grüße aus München 🇩🇪';

        $encrypted = $this->service->encrypt($original);
        $decrypted = $this->service->decrypt($encrypted['encrypted'], $encrypted['iv']);

        $this->assertEquals($original, $decrypted);
    }

    public function test_encrypts_long_text(): void
    {
        $original = str_repeat('Lorem ipsum dolor sit amet. ', 1000);

        $encrypted = $this->service->encrypt($original);
        $decrypted = $this->service->decrypt($encrypted['encrypted'], $encrypted['iv']);

        $this->assertEquals($original, $decrypted);
    }

    public function test_encrypt_for_storage_format(): void
    {
        $content = 'Test message';

        $result = $this->service->encryptForStorage($content);

        $this->assertArrayHasKey('content', $result);
        $this->assertArrayHasKey('content_iv', $result);
        $this->assertNotEquals($content, $result['content']);
    }

    public function test_decrypt_from_storage(): void
    {
        $original = 'Test message';
        $stored = $this->service->encryptForStorage($original);

        $decrypted = $this->service->decryptFromStorage($stored['content'], $stored['content_iv']);

        $this->assertEquals($original, $decrypted);
    }

    public function test_decrypt_from_storage_handles_null_iv(): void
    {
        $content = 'Unencrypted content';

        $result = $this->service->decryptFromStorage($content, null);

        $this->assertEquals($content, $result);
    }
}
```

**Akzeptanzkriterien:**
- [ ] Verschlüsselung funktioniert
- [ ] Entschlüsselung gibt Originaltext zurück
- [ ] Verschiedene IVs für gleichen Text
- [ ] Unicode wird korrekt behandelt

---

## 3.3 Broadcast Events [BE]

### 3.3.1 NewMessage Event erstellen
- [ ] **Erledigt**

**Durchführung:**
```bash
php artisan make:event NewMessage
```

**Datei:** `app/Events/NewMessage.php`
```php
<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewMessage implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Message $message,
        public string $decryptedContent
    ) {}

    public function broadcastOn(): array
    {
        $channelName = $this->message->messageable_type === 'channel'
            ? 'channel.' . $this->message->messageable_id
            : 'conversation.' . $this->message->messageable_id;

        return [
            new PrivateChannel($channelName),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'content' => $this->decryptedContent,
            'content_type' => $this->message->content_type,
            'sender' => [
                'id' => $this->message->sender->id,
                'username' => $this->message->sender->username,
                'avatar_url' => $this->message->sender->avatar_url,
            ],
            'parent_id' => $this->message->parent_id,
            'created_at' => $this->message->created_at->toIso8601String(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.new';
    }
}
```

---

### 3.3.2 MessageEdited Event erstellen
- [ ] **Erledigt**

**Durchführung:**
```bash
php artisan make:event MessageEdited
```

**Datei:** `app/Events/MessageEdited.php`
```php
<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageEdited implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Message $message,
        public string $decryptedContent
    ) {}

    public function broadcastOn(): array
    {
        $channelName = $this->message->messageable_type === 'channel'
            ? 'channel.' . $this->message->messageable_id
            : 'conversation.' . $this->message->messageable_id;

        return [new PrivateChannel($channelName)];
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'content' => $this->decryptedContent,
            'edited_at' => $this->message->edited_at->toIso8601String(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.edited';
    }
}
```

---

### 3.3.3 MessageDeleted Event erstellen
- [ ] **Erledigt**

**Durchführung:**
```bash
php artisan make:event MessageDeleted
```

**Datei:** `app/Events/MessageDeleted.php`
```php
<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageDeleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    private string $messageableType;
    private int $messageableId;
    private int $messageId;

    public function __construct(Message $message)
    {
        // Daten speichern bevor die Message gelöscht wird
        $this->messageableType = $message->messageable_type;
        $this->messageableId = $message->messageable_id;
        $this->messageId = $message->id;
    }

    public function broadcastOn(): array
    {
        $channelName = $this->messageableType === 'channel'
            ? 'channel.' . $this->messageableId
            : 'conversation.' . $this->messageableId;

        return [new PrivateChannel($channelName)];
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->messageId,
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.deleted';
    }
}
```

---

### 3.3.4 UserTyping Event erstellen
- [ ] **Erledigt**

**Durchführung:**
```bash
php artisan make:event UserTyping
```

**Datei:** `app/Events/UserTyping.php`
```php
<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserTyping implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public User $user,
        public string $messageableType,
        public int $messageableId
    ) {}

    public function broadcastOn(): array
    {
        $channelName = $this->messageableType === 'channel'
            ? 'channel.' . $this->messageableId
            : 'conversation.' . $this->messageableId;

        return [new PrivateChannel($channelName)];
    }

    public function broadcastWith(): array
    {
        return [
            'user' => [
                'id' => $this->user->id,
                'username' => $this->user->username,
            ],
            'timestamp' => now()->toIso8601String(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'user.typing';
    }
}
```

---

### 3.3.5 UserStatusChanged Event erstellen
- [ ] **Erledigt**

**Durchführung:**
```bash
php artisan make:event UserStatusChanged
```

**Datei:** `app/Events/UserStatusChanged.php`
```php
<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public User $user
    ) {}

    public function broadcastOn(): array
    {
        // An alle Firmen broadcasten in denen der User Mitglied ist
        $channels = $this->user->companies->map(function ($company) {
            return new PrivateChannel('company.' . $company->id);
        })->toArray();

        return $channels;
    }

    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->user->id,
            'status' => $this->user->status,
            'status_text' => $this->user->status_text,
        ];
    }

    public function broadcastAs(): string
    {
        return 'user.status_changed';
    }
}
```

---

## 3.4 Message Controller [BE]

### 3.4.1 MessageController erstellen
- [ ] **Erledigt**

**Durchführung:**
```bash
php artisan make:controller Api/MessageController
```

**Datei:** `app/Http/Controllers/Api/MessageController.php`
```php
<?php

namespace App\Http\Controllers\Api;

use App\Events\MessageDeleted;
use App\Events\MessageEdited;
use App\Events\NewMessage;
use App\Events\UserTyping;
use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\DirectConversation;
use App\Models\Message;
use App\Services\MessageEncryptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function __construct(
        private MessageEncryptionService $encryptionService
    ) {}

    // Methoden folgen in den nächsten Tasks
}
```

---

### 3.4.2 Endpoint: GET /api/channels/{channel}/messages
- [ ] **Erledigt**

**Beschreibung:** Nachrichten eines Channels laden mit Pagination.

**Query Parameters:**
- `before` (optional): Message-ID für Pagination
- `limit` (optional): Anzahl der Nachrichten (default 50, max 100)

**Response (200):**
```json
{
    "messages": [
        {
            "id": 1,
            "content": "Entschlüsselter Text",
            "content_type": "text",
            "sender": {
                "id": 1,
                "username": "Max",
                "avatar_url": "..."
            },
            "reactions": [
                {"emoji": "👍", "count": 2, "user_ids": [1, 3]}
            ],
            "parent_id": null,
            "edited_at": null,
            "created_at": "2024-..."
        }
    ],
    "has_more": true
}
```

**Implementierung:**
```php
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

**Unit Test:** `tests/Feature/Api/Message/ChannelMessagesTest.php`
```php
<?php

namespace Tests\Feature\Api\Message;

use App\Models\User;
use App\Models\Channel;
use App\Models\Message;
use App\Services\MessageEncryptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChannelMessagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_load_channel_messages(): void
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->create();
        $channel->members()->attach($user->id);

        $encryptionService = app(MessageEncryptionService::class);
        $encrypted = $encryptionService->encryptForStorage('Test message');

        Message::factory()->create([
            'messageable_type' => 'channel',
            'messageable_id' => $channel->id,
            'content' => $encrypted['content'],
            'content_iv' => $encrypted['content_iv'],
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/channels/{$channel->id}/messages");

        $response->assertOk()
            ->assertJsonStructure([
                'messages' => [
                    '*' => ['id', 'content', 'sender', 'created_at']
                ],
                'has_more'
            ]);
    }

    public function test_non_member_cannot_load_messages(): void
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->create();

        $response = $this->actingAs($user)
            ->getJson("/api/channels/{$channel->id}/messages");

        $response->assertStatus(403);
    }

    public function test_messages_are_decrypted(): void
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->create();
        $channel->members()->attach($user->id);

        $encryptionService = app(MessageEncryptionService::class);
        $originalContent = 'Hello, World!';
        $encrypted = $encryptionService->encryptForStorage($originalContent);

        Message::factory()->create([
            'messageable_type' => 'channel',
            'messageable_id' => $channel->id,
            'content' => $encrypted['content'],
            'content_iv' => $encrypted['content_iv'],
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/channels/{$channel->id}/messages");

        $response->assertOk()
            ->assertJsonFragment(['content' => $originalContent]);
    }

    public function test_pagination_with_before_parameter(): void
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->create();
        $channel->members()->attach($user->id);

        $encryptionService = app(MessageEncryptionService::class);

        // Erstelle 5 Nachrichten
        $messages = [];
        for ($i = 1; $i <= 5; $i++) {
            $encrypted = $encryptionService->encryptForStorage("Message {$i}");
            $messages[] = Message::factory()->create([
                'messageable_type' => 'channel',
                'messageable_id' => $channel->id,
                'content' => $encrypted['content'],
                'content_iv' => $encrypted['content_iv'],
                'created_at' => now()->addMinutes($i),
            ]);
        }

        // Lade Nachrichten vor der letzten
        $response = $this->actingAs($user)
            ->getJson("/api/channels/{$channel->id}/messages?before={$messages[4]->id}&limit=2");

        $response->assertOk()
            ->assertJsonCount(2, 'messages');
    }

    public function test_default_loads_last_3_days(): void
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->create();
        $channel->members()->attach($user->id);

        $encryptionService = app(MessageEncryptionService::class);

        // Alte Nachricht (5 Tage)
        $encrypted = $encryptionService->encryptForStorage('Old message');
        Message::factory()->create([
            'messageable_type' => 'channel',
            'messageable_id' => $channel->id,
            'content' => $encrypted['content'],
            'content_iv' => $encrypted['content_iv'],
            'created_at' => now()->subDays(5),
        ]);

        // Neue Nachricht (1 Tag)
        $encrypted = $encryptionService->encryptForStorage('New message');
        Message::factory()->create([
            'messageable_type' => 'channel',
            'messageable_id' => $channel->id,
            'content' => $encrypted['content'],
            'content_iv' => $encrypted['content_iv'],
            'created_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/channels/{$channel->id}/messages");

        $response->assertOk()
            ->assertJsonCount(1, 'messages')
            ->assertJsonFragment(['content' => 'New message']);
    }
}
```

**Akzeptanzkriterien:**
- [ ] Nur Channel-Mitglieder können lesen
- [ ] Default lädt letzte 3 Tage
- [ ] Pagination mit `before` funktioniert
- [ ] Content ist entschlüsselt
- [ ] Reactions sind aggregiert

---

### 3.4.3 Endpoint: POST /api/channels/{channel}/messages
- [ ] **Erledigt**

**Beschreibung:** Nachricht in Channel senden.

**Request:**
```json
{
    "content": "Hallo zusammen!",
    "parent_id": null
}
```

**Response (201):**
```json
{
    "message": "Message sent",
    "data": {
        "id": 1,
        "content": "Hallo zusammen!",
        ...
    }
}
```

**Implementierung:**
```php
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
```

**Unit Test:** `tests/Feature/Api/Message/SendChannelMessageTest.php`
```php
<?php

namespace Tests\Feature\Api\Message;

use App\Events\NewMessage;
use App\Models\User;
use App\Models\Channel;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class SendChannelMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_send_message(): void
    {
        Event::fake([NewMessage::class]);

        $user = User::factory()->create();
        $channel = Channel::factory()->create();
        $channel->members()->attach($user->id);

        $response = $this->actingAs($user)
            ->postJson("/api/channels/{$channel->id}/messages", [
                'content' => 'Hello, World!',
            ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['content' => 'Hello, World!']);

        $this->assertDatabaseHas('messages', [
            'messageable_type' => 'channel',
            'messageable_id' => $channel->id,
            'sender_id' => $user->id,
        ]);

        Event::assertDispatched(NewMessage::class);
    }

    public function test_non_member_cannot_send_message(): void
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->create();

        $response = $this->actingAs($user)
            ->postJson("/api/channels/{$channel->id}/messages", [
                'content' => 'Hello!',
            ]);

        $response->assertStatus(403);
    }

    public function test_message_is_encrypted_in_database(): void
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->create();
        $channel->members()->attach($user->id);

        $this->actingAs($user)
            ->postJson("/api/channels/{$channel->id}/messages", [
                'content' => 'Secret message',
            ]);

        $message = Message::first();

        // Content in DB sollte nicht der Klartext sein
        $this->assertNotEquals('Secret message', $message->content);
        $this->assertNotNull($message->content_iv);
    }

    public function test_can_reply_to_message(): void
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->create();
        $channel->members()->attach($user->id);

        $parentMessage = Message::factory()->create([
            'messageable_type' => 'channel',
            'messageable_id' => $channel->id,
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/channels/{$channel->id}/messages", [
                'content' => 'This is a reply',
                'parent_id' => $parentMessage->id,
            ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['parent_id' => $parentMessage->id]);
    }

    public function test_cannot_reply_to_message_from_different_channel(): void
    {
        $user = User::factory()->create();
        $channel1 = Channel::factory()->create();
        $channel2 = Channel::factory()->create();
        $channel1->members()->attach($user->id);

        $parentMessage = Message::factory()->create([
            'messageable_type' => 'channel',
            'messageable_id' => $channel2->id,
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/channels/{$channel1->id}/messages", [
                'content' => 'Invalid reply',
                'parent_id' => $parentMessage->id,
            ]);

        $response->assertStatus(422);
    }
}
```

**Akzeptanzkriterien:**
- [ ] Nur Channel-Mitglieder können senden
- [ ] Nachricht wird verschlüsselt gespeichert
- [ ] NewMessage Event wird gebroadcastet
- [ ] Reply auf andere Nachrichten möglich
- [ ] Reply-Validierung (gleiches Channel)

---

### 3.4.4 Endpoint: PUT /api/messages/{message}
- [ ] **Erledigt**

**Beschreibung:** Nachricht bearbeiten (nur eigene, innerhalb 24h).

**Request:**
```json
{
    "content": "Bearbeiteter Text"
}
```

**Response (200):**
```json
{
    "message": "Message updated",
    "data": { ... }
}
```

**Implementierung:**
```php
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
```

**Unit Test:** `tests/Feature/Api/Message/EditMessageTest.php`
```php
<?php

namespace Tests\Feature\Api\Message;

use App\Events\MessageEdited;
use App\Models\User;
use App\Models\Channel;
use App\Models\Message;
use App\Services\MessageEncryptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class EditMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_edit_own_message(): void
    {
        Event::fake([MessageEdited::class]);

        $user = User::factory()->create();
        $channel = Channel::factory()->create();
        $channel->members()->attach($user->id);

        $encryptionService = app(MessageEncryptionService::class);
        $encrypted = $encryptionService->encryptForStorage('Original');

        $message = Message::factory()->create([
            'messageable_type' => 'channel',
            'messageable_id' => $channel->id,
            'sender_id' => $user->id,
            'content' => $encrypted['content'],
            'content_iv' => $encrypted['content_iv'],
        ]);

        $response = $this->actingAs($user)
            ->putJson("/api/messages/{$message->id}", [
                'content' => 'Edited content',
            ]);

        $response->assertOk()
            ->assertJsonFragment(['content' => 'Edited content']);

        $this->assertNotNull($message->fresh()->edited_at);
        Event::assertDispatched(MessageEdited::class);
    }

    public function test_user_cannot_edit_others_message(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $message = Message::factory()->create([
            'sender_id' => $other->id,
        ]);

        $response = $this->actingAs($user)
            ->putJson("/api/messages/{$message->id}", [
                'content' => 'Hacked!',
            ]);

        $response->assertStatus(403);
    }

    public function test_cannot_edit_after_24_hours(): void
    {
        $user = User::factory()->create();

        $message = Message::factory()->create([
            'sender_id' => $user->id,
            'created_at' => now()->subHours(25),
        ]);

        $response = $this->actingAs($user)
            ->putJson("/api/messages/{$message->id}", [
                'content' => 'Too late!',
            ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'Messages can only be edited within 24 hours']);
    }
}
```

**Akzeptanzkriterien:**
- [ ] Nur eigene Nachrichten bearbeitbar
- [ ] 24-Stunden-Limit
- [ ] edited_at wird gesetzt
- [ ] MessageEdited Event wird gebroadcastet

---

### 3.4.5 Endpoint: DELETE /api/messages/{message}
- [ ] **Erledigt**

**Beschreibung:** Nachricht löschen (nur eigene oder als Admin).

**Response (200):**
```json
{
    "message": "Message deleted"
}
```

**Implementierung:**
```php
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
```

**Unit Test:** `tests/Feature/Api/Message/DeleteMessageTest.php`
```php
<?php

namespace Tests\Feature\Api\Message;

use App\Events\MessageDeleted;
use App\Models\User;
use App\Models\Company;
use App\Models\Channel;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class DeleteMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_delete_own_message(): void
    {
        Event::fake([MessageDeleted::class]);

        $user = User::factory()->create();

        $message = Message::factory()->create([
            'sender_id' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->deleteJson("/api/messages/{$message->id}");

        $response->assertOk();
        $this->assertSoftDeleted($message);
        Event::assertDispatched(MessageDeleted::class);
    }

    public function test_admin_can_delete_any_message_in_company(): void
    {
        $admin = User::factory()->create();
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $company->members()->attach($admin->id, ['role' => 'admin']);

        $channel = Channel::factory()->create(['company_id' => $company->id]);

        $message = Message::factory()->create([
            'messageable_type' => 'channel',
            'messageable_id' => $channel->id,
            'sender_id' => $user->id,
        ]);

        $response = $this->actingAs($admin)
            ->deleteJson("/api/messages/{$message->id}");

        $response->assertOk();
        $this->assertSoftDeleted($message);
    }

    public function test_user_cannot_delete_others_message(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $message = Message::factory()->create([
            'sender_id' => $other->id,
        ]);

        $response = $this->actingAs($user)
            ->deleteJson("/api/messages/{$message->id}");

        $response->assertStatus(403);
    }
}
```

**Akzeptanzkriterien:**
- [ ] User kann eigene Nachrichten löschen
- [ ] Admin kann alle Nachrichten im Channel löschen
- [ ] Soft Delete (Nachricht bleibt in DB)
- [ ] MessageDeleted Event wird gebroadcastet

---

### 3.4.6 Endpoint: POST /api/channels/{channel}/typing
- [ ] **Erledigt**

**Beschreibung:** Typing-Indicator senden.

**Response (200):**
```json
{
    "message": "Typing indicator sent"
}
```

**Implementierung:**
```php
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
```

**Akzeptanzkriterien:**
- [ ] Nur Channel-Mitglieder können Typing senden
- [ ] Event wird an andere Mitglieder gebroadcastet

---

## 3.5 Routes & Tests [BE]

### 3.5.1 Message Routes definieren
- [ ] **Erledigt**

**Datei:** `routes/api.php` ergänzen:
```php
use App\Http\Controllers\Api\MessageController;

Route::middleware('auth:sanctum')->group(function () {
    // ... bestehende Routes ...

    // Channel Messages
    Route::prefix('channels/{channel}')->group(function () {
        Route::get('messages', [MessageController::class, 'channelMessages']);
        Route::post('messages', [MessageController::class, 'storeChannelMessage']);
        Route::post('typing', [MessageController::class, 'typing']);
    });

    // Message CRUD
    Route::prefix('messages')->group(function () {
        Route::put('{message}', [MessageController::class, 'update']);
        Route::delete('{message}', [MessageController::class, 'destroy']);
    });
});
```

---

### 3.5.2 Alle Phase 3 Tests ausführen
- [ ] **Erledigt**

**Durchführung:**
```bash
php artisan test --filter=Message
php artisan test --filter=Broadcasting
php artisan test --filter=Encryption
php artisan test
```

**Akzeptanzkriterien:**
- [ ] Alle Tests grün
- [ ] Mindestens 70 Tests insgesamt

---

### 3.5.3 WebSocket manuell testen
- [ ] **Erledigt**

**Durchführung:**
```bash
# Terminal 1: Reverb starten
php artisan reverb:start

# Terminal 2: Laravel Server
php artisan serve

# Terminal 3: Queue Worker (für async Events)
php artisan queue:work
```

**WebSocket Test mit JavaScript (Browser Console):**
```javascript
// Pusher/Echo Test (nach Frontend-Setup)
// Dieser Test ist nach Phase 7 möglich
```

---

### 3.5.4 Git Commit & Tag
- [ ] **Erledigt**

**Durchführung:**
```bash
git add .
git commit -m "Phase 3: Real-Time Chat - WebSockets, Encryption, Messages"
git tag v0.3.0
```

---

## Phase 3 Zusammenfassung

### Erstellte Dateien
- 1 Service (MessageEncryptionService)
- 5 Events (NewMessage, MessageEdited, MessageDeleted, UserTyping, UserStatusChanged)
- 1 Controller (MessageController)
- Broadcasting Channel Definitionen
- ~10 neue Test-Dateien

### Neue API Endpoints
| Methode | Endpoint | Beschreibung |
|---------|----------|--------------|
| GET | /api/channels/{id}/messages | Nachrichten laden |
| POST | /api/channels/{id}/messages | Nachricht senden |
| POST | /api/channels/{id}/typing | Typing-Indicator |
| PUT | /api/messages/{id} | Nachricht bearbeiten |
| DELETE | /api/messages/{id} | Nachricht löschen |

### WebSocket Events
| Event | Channel | Beschreibung |
|-------|---------|--------------|
| message.new | private-channel.{id} | Neue Nachricht |
| message.edited | private-channel.{id} | Nachricht bearbeitet |
| message.deleted | private-channel.{id} | Nachricht gelöscht |
| user.typing | private-channel.{id} | User tippt |
| user.status_changed | private-company.{id} | User-Status geändert |

### Sicherheitsfeatures
- AES-256-CBC Verschlüsselung für alle Nachrichten
- Separate IV pro Nachricht
- Channel-basierte Autorisierung für WebSockets

### Nächste Phase
→ Weiter mit `phase-4-direct-messages.md`
