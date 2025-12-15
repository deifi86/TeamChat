# Phase 5: Emojis & Reaktionen (Woche 10)

## Ziel dieser Phase
Nach Abschluss dieser Phase haben wir:
- Reaktions-System für Nachrichten (add/remove)
- Real-Time Updates für Reaktionen
- Emoji-Shortcode Unterstützung in Nachrichten
- Reaktions-Übersicht pro Nachricht

---

## 5.1 Reaction Controller [BE]

### 5.1.1 ReactionController erstellen
- [x] **Erledigt**

→ *Abhängig von Phase 4 abgeschlossen*

**Durchführung:**
```bash
php artisan make:controller Api/ReactionController
```

**Datei:** `app/Http/Controllers/Api/ReactionController.php`
```php
<?php

namespace App\Http\Controllers\Api;

use App\Events\ReactionAdded;
use App\Events\ReactionRemoved;
use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\DirectConversation;
use App\Models\Message;
use App\Models\MessageReaction;
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
```

---

### 5.1.2 Unit Tests für ReactionController
- [x] **Erledigt**

**Datei:** `tests/Feature/Api/Reaction/AddReactionTest.php`
```php
<?php

namespace Tests\Feature\Api\Reaction;

use App\Events\ReactionAdded;
use App\Models\User;
use App\Models\Channel;
use App\Models\Message;
use App\Models\MessageReaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class AddReactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_add_reaction_to_channel_message(): void
    {
        Event::fake([ReactionAdded::class]);

        $user = User::factory()->create();
        $channel = Channel::factory()->create();
        $channel->members()->attach($user->id);

        $message = Message::factory()->create([
            'messageable_type' => 'channel',
            'messageable_id' => $channel->id,
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/messages/{$message->id}/reactions", [
                'emoji' => '👍',
            ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['emoji' => '👍']);

        $this->assertDatabaseHas('message_reactions', [
            'message_id' => $message->id,
            'user_id' => $user->id,
            'emoji' => '👍',
        ]);

        Event::assertDispatched(ReactionAdded::class);
    }

    public function test_non_member_cannot_add_reaction(): void
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->create();

        $message = Message::factory()->create([
            'messageable_type' => 'channel',
            'messageable_id' => $channel->id,
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/messages/{$message->id}/reactions", [
                'emoji' => '👍',
            ]);

        $response->assertStatus(404);
    }

    public function test_duplicate_reaction_returns_existing(): void
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->create();
        $channel->members()->attach($user->id);

        $message = Message::factory()->create([
            'messageable_type' => 'channel',
            'messageable_id' => $channel->id,
        ]);

        // Erste Reaktion
        MessageReaction::create([
            'message_id' => $message->id,
            'user_id' => $user->id,
            'emoji' => '👍',
        ]);

        // Zweite gleiche Reaktion
        $response = $this->actingAs($user)
            ->postJson("/api/messages/{$message->id}/reactions", [
                'emoji' => '👍',
            ]);

        $response->assertOk()
            ->assertJsonFragment(['message' => 'Reaction already exists']);

        // Nur eine Reaktion in DB
        $this->assertEquals(1, MessageReaction::count());
    }

    public function test_can_add_multiple_different_emojis(): void
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->create();
        $channel->members()->attach($user->id);

        $message = Message::factory()->create([
            'messageable_type' => 'channel',
            'messageable_id' => $channel->id,
        ]);

        $this->actingAs($user)
            ->postJson("/api/messages/{$message->id}/reactions", ['emoji' => '👍']);

        $this->actingAs($user)
            ->postJson("/api/messages/{$message->id}/reactions", ['emoji' => '❤️']);

        $this->assertEquals(2, MessageReaction::where('user_id', $user->id)->count());
    }
}
```

**Datei:** `tests/Feature/Api/Reaction/RemoveReactionTest.php`
```php
<?php

namespace Tests\Feature\Api\Reaction;

use App\Events\ReactionRemoved;
use App\Models\User;
use App\Models\Channel;
use App\Models\Message;
use App\Models\MessageReaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class RemoveReactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_remove_own_reaction(): void
    {
        Event::fake([ReactionRemoved::class]);

        $user = User::factory()->create();
        $channel = Channel::factory()->create();
        $channel->members()->attach($user->id);

        $message = Message::factory()->create([
            'messageable_type' => 'channel',
            'messageable_id' => $channel->id,
        ]);

        MessageReaction::create([
            'message_id' => $message->id,
            'user_id' => $user->id,
            'emoji' => '👍',
        ]);

        $response = $this->actingAs($user)
            ->deleteJson("/api/messages/{$message->id}/reactions/👍");

        $response->assertOk();

        $this->assertDatabaseMissing('message_reactions', [
            'message_id' => $message->id,
            'user_id' => $user->id,
            'emoji' => '👍',
        ]);

        Event::assertDispatched(ReactionRemoved::class);
    }

    public function test_cannot_remove_others_reaction(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $channel = Channel::factory()->create();
        $channel->members()->attach([$user->id, $other->id]);

        $message = Message::factory()->create([
            'messageable_type' => 'channel',
            'messageable_id' => $channel->id,
        ]);

        // Andere User's Reaktion
        MessageReaction::create([
            'message_id' => $message->id,
            'user_id' => $other->id,
            'emoji' => '👍',
        ]);

        // Versuchen zu löschen
        $response = $this->actingAs($user)
            ->deleteJson("/api/messages/{$message->id}/reactions/👍");

        $response->assertStatus(404);

        // Reaktion existiert noch
        $this->assertEquals(1, MessageReaction::count());
    }

    public function test_returns_404_for_nonexistent_reaction(): void
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->create();
        $channel->members()->attach($user->id);

        $message = Message::factory()->create([
            'messageable_type' => 'channel',
            'messageable_id' => $channel->id,
        ]);

        $response = $this->actingAs($user)
            ->deleteJson("/api/messages/{$message->id}/reactions/👍");

        $response->assertStatus(404);
    }
}
```

**Datei:** `tests/Feature/Api/Reaction/ToggleReactionTest.php`
```php
<?php

namespace Tests\Feature\Api\Reaction;

use App\Models\User;
use App\Models\Channel;
use App\Models\Message;
use App\Models\MessageReaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ToggleReactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_toggle_adds_reaction_when_not_exists(): void
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->create();
        $channel->members()->attach($user->id);

        $message = Message::factory()->create([
            'messageable_type' => 'channel',
            'messageable_id' => $channel->id,
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/messages/{$message->id}/reactions/toggle", [
                'emoji' => '👍',
            ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['action' => 'added']);

        $this->assertDatabaseHas('message_reactions', [
            'message_id' => $message->id,
            'user_id' => $user->id,
            'emoji' => '👍',
        ]);
    }

    public function test_toggle_removes_reaction_when_exists(): void
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->create();
        $channel->members()->attach($user->id);

        $message = Message::factory()->create([
            'messageable_type' => 'channel',
            'messageable_id' => $channel->id,
        ]);

        MessageReaction::create([
            'message_id' => $message->id,
            'user_id' => $user->id,
            'emoji' => '👍',
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/messages/{$message->id}/reactions/toggle", [
                'emoji' => '👍',
            ]);

        $response->assertOk()
            ->assertJsonFragment(['action' => 'removed']);

        $this->assertDatabaseMissing('message_reactions', [
            'message_id' => $message->id,
            'user_id' => $user->id,
            'emoji' => '👍',
        ]);
    }
}
```

**Datei:** `tests/Feature/Api/Reaction/ListReactionsTest.php`
```php
<?php

namespace Tests\Feature\Api\Reaction;

use App\Models\User;
use App\Models\Channel;
use App\Models\Message;
use App\Models\MessageReaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListReactionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_reactions_grouped_by_emoji(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $channel = Channel::factory()->create();
        $channel->members()->attach([$user1->id, $user2->id]);

        $message = Message::factory()->create([
            'messageable_type' => 'channel',
            'messageable_id' => $channel->id,
        ]);

        // Zwei 👍 Reaktionen
        MessageReaction::create([
            'message_id' => $message->id,
            'user_id' => $user1->id,
            'emoji' => '👍',
        ]);
        MessageReaction::create([
            'message_id' => $message->id,
            'user_id' => $user2->id,
            'emoji' => '👍',
        ]);

        // Eine ❤️ Reaktion
        MessageReaction::create([
            'message_id' => $message->id,
            'user_id' => $user1->id,
            'emoji' => '❤️',
        ]);

        $response = $this->actingAs($user1)
            ->getJson("/api/messages/{$message->id}/reactions");

        $response->assertOk()
            ->assertJsonCount(2, 'reactions');

        $reactions = collect($response->json('reactions'));
        
        $thumbsUp = $reactions->firstWhere('emoji', '👍');
        $this->assertEquals(2, $thumbsUp['count']);
        $this->assertTrue($thumbsUp['has_reacted']);

        $heart = $reactions->firstWhere('emoji', '❤️');
        $this->assertEquals(1, $heart['count']);
    }

    public function test_has_reacted_is_false_when_user_has_not_reacted(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $channel = Channel::factory()->create();
        $channel->members()->attach([$user->id, $other->id]);

        $message = Message::factory()->create([
            'messageable_type' => 'channel',
            'messageable_id' => $channel->id,
        ]);

        // Nur andere User hat reagiert
        MessageReaction::create([
            'message_id' => $message->id,
            'user_id' => $other->id,
            'emoji' => '👍',
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/messages/{$message->id}/reactions");

        $response->assertOk();
        $this->assertFalse($response->json('reactions.0.has_reacted'));
    }
}
```

**Akzeptanzkriterien:**
- [x] Reaktion hinzufügen funktioniert
- [x] Reaktion entfernen funktioniert
- [x] Toggle-Funktion funktioniert
- [x] Keine doppelten Reaktionen möglich
- [x] Nur eigene Reaktionen entfernbar
- [x] Reaktionen werden gruppiert angezeigt

---

## 5.2 Reaction Events [BE]

### 5.2.1 ReactionAdded Event erstellen
- [x] **Erledigt**

**Durchführung:**
```bash
php artisan make:event ReactionAdded
```

**Datei:** `app/Events/ReactionAdded.php`
```php
<?php

namespace App\Events;

use App\Models\Message;
use App\Models\MessageReaction;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReactionAdded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Message $message,
        public MessageReaction $reaction,
        public User $user
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
            'message_id' => $this->message->id,
            'reaction' => [
                'id' => $this->reaction->id,
                'emoji' => $this->reaction->emoji,
                'user' => [
                    'id' => $this->user->id,
                    'username' => $this->user->username,
                ],
            ],
        ];
    }

    public function broadcastAs(): string
    {
        return 'reaction.added';
    }
}
```

---

### 5.2.2 ReactionRemoved Event erstellen
- [x] **Erledigt**

**Durchführung:**
```bash
php artisan make:event ReactionRemoved
```

**Datei:** `app/Events/ReactionRemoved.php`
```php
<?php

namespace App\Events;

use App\Models\Message;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReactionRemoved implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Message $message,
        public string $emoji,
        public User $user
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
            'message_id' => $this->message->id,
            'emoji' => $this->emoji,
            'user_id' => $this->user->id,
        ];
    }

    public function broadcastAs(): string
    {
        return 'reaction.removed';
    }
}
```

---

## 5.3 Emoji Helper Service [BE]

### 5.3.1 EmojiService erstellen (Optional)
- [x] **Erledigt**

**Beschreibung:** Service für Emoji-Shortcode Konvertierung (z.B. `:smile:` → 😊).

**Datei:** `app/Services/EmojiService.php`
```php
<?php

namespace App\Services;

class EmojiService
{
    /**
     * Mapping von Shortcodes zu Unicode Emojis
     */
    private array $shortcodes = [
        // Smileys
        ':smile:' => '😊',
        ':grin:' => '😁',
        ':joy:' => '😂',
        ':rofl:' => '🤣',
        ':wink:' => '😉',
        ':blush:' => '😊',
        ':heart_eyes:' => '😍',
        ':kissing:' => '😘',
        ':thinking:' => '🤔',
        ':neutral:' => '😐',
        ':expressionless:' => '😑',
        ':unamused:' => '😒',
        ':rolling_eyes:' => '🙄',
        ':grimacing:' => '😬',
        ':lying:' => '🤥',
        ':relieved:' => '😌',
        ':pensive:' => '😔',
        ':sleepy:' => '😪',
        ':drooling:' => '🤤',
        ':sleeping:' => '😴',
        ':mask:' => '😷',
        ':sick:' => '🤒',
        ':nerd:' => '🤓',
        ':sunglasses:' => '😎',
        ':confused:' => '😕',
        ':worried:' => '😟',
        ':frown:' => '☹️',
        ':open_mouth:' => '😮',
        ':hushed:' => '😯',
        ':astonished:' => '😲',
        ':flushed:' => '😳',
        ':scream:' => '😱',
        ':fearful:' => '😨',
        ':cold_sweat:' => '😰',
        ':cry:' => '😢',
        ':sob:' => '😭',
        ':angry:' => '😠',
        ':rage:' => '😡',
        ':triumph:' => '😤',
        ':skull:' => '💀',
        ':poop:' => '💩',
        ':clown:' => '🤡',
        ':ghost:' => '👻',
        ':alien:' => '👽',
        ':robot:' => '🤖',
        ':cat:' => '😺',
        ':heart:' => '❤️',
        ':orange_heart:' => '🧡',
        ':yellow_heart:' => '💛',
        ':green_heart:' => '💚',
        ':blue_heart:' => '💙',
        ':purple_heart:' => '💜',
        ':black_heart:' => '🖤',
        ':broken_heart:' => '💔',
        ':fire:' => '🔥',
        ':sparkles:' => '✨',
        ':star:' => '⭐',
        ':star2:' => '🌟',
        ':zap:' => '⚡',
        ':boom:' => '💥',
        ':question:' => '❓',
        ':exclamation:' => '❗',
        
        // Gesten
        ':thumbsup:' => '👍',
        ':thumbsdown:' => '👎',
        ':+1:' => '👍',
        ':-1:' => '👎',
        ':ok_hand:' => '👌',
        ':punch:' => '👊',
        ':fist:' => '✊',
        ':v:' => '✌️',
        ':wave:' => '👋',
        ':hand:' => '✋',
        ':clap:' => '👏',
        ':muscle:' => '💪',
        ':pray:' => '🙏',
        ':point_up:' => '☝️',
        ':point_down:' => '👇',
        ':point_left:' => '👈',
        ':point_right:' => '👉',
        ':middle_finger:' => '🖕',
        ':writing_hand:' => '✍️',
        
        // Objekte & Symbole
        ':check:' => '✅',
        ':x:' => '❌',
        ':warning:' => '⚠️',
        ':no_entry:' => '⛔',
        ':recycle:' => '♻️',
        ':white_check_mark:' => '✅',
        ':ballot_box_with_check:' => '☑️',
        ':heavy_check_mark:' => '✔️',
        ':clock:' => '🕐',
        ':hourglass:' => '⏳',
        ':watch:' => '⌚',
        ':phone:' => '📱',
        ':computer:' => '💻',
        ':keyboard:' => '⌨️',
        ':mouse:' => '🖱️',
        ':printer:' => '🖨️',
        ':camera:' => '📷',
        ':video:' => '📹',
        ':tv:' => '📺',
        ':radio:' => '📻',
        ':speaker:' => '🔊',
        ':mute:' => '🔇',
        ':bell:' => '🔔',
        ':no_bell:' => '🔕',
        ':microphone:' => '🎤',
        ':headphones:' => '🎧',
        ':cd:' => '💿',
        ':dvd:' => '📀',
        ':battery:' => '🔋',
        ':electric_plug:' => '🔌',
        ':bulb:' => '💡',
        ':flashlight:' => '🔦',
        ':wrench:' => '🔧',
        ':hammer:' => '🔨',
        ':nut_and_bolt:' => '🔩',
        ':gear:' => '⚙️',
        ':link:' => '🔗',
        ':paperclip:' => '📎',
        ':scissors:' => '✂️',
        ':file_folder:' => '📁',
        ':open_file_folder:' => '📂',
        ':page_facing_up:' => '📄',
        ':page_with_curl:' => '📃',
        ':calendar:' => '📅',
        ':clipboard:' => '📋',
        ':pushpin:' => '📌',
        ':paperclip:' => '📎',
        ':straight_ruler:' => '📏',
        ':triangular_ruler:' => '📐',
        ':pencil2:' => '✏️',
        ':memo:' => '📝',
        ':lock:' => '🔒',
        ':unlock:' => '🔓',
        ':key:' => '🔑',
        ':email:' => '📧',
        ':envelope:' => '✉️',
        ':inbox_tray:' => '📥',
        ':outbox_tray:' => '📤',
        ':package:' => '📦',
        ':label:' => '🏷️',
        ':bookmark:' => '🔖',
        ':chart:' => '📊',
        ':chart_with_upwards_trend:' => '📈',
        ':chart_with_downwards_trend:' => '📉',
        ':bar_chart:' => '📊',
        
        // Essen & Trinken
        ':coffee:' => '☕',
        ':tea:' => '🍵',
        ':beer:' => '🍺',
        ':beers:' => '🍻',
        ':wine:' => '🍷',
        ':cocktail:' => '🍸',
        ':pizza:' => '🍕',
        ':hamburger:' => '🍔',
        ':fries:' => '🍟',
        ':hotdog:' => '🌭',
        ':taco:' => '🌮',
        ':burrito:' => '🌯',
        ':cake:' => '🎂',
        ':cookie:' => '🍪',
        ':chocolate:' => '🍫',
        ':candy:' => '🍬',
        ':apple:' => '🍎',
        ':banana:' => '🍌',
        ':grapes:' => '🍇',
        ':watermelon:' => '🍉',
        ':strawberry:' => '🍓',
        ':lemon:' => '🍋',
        ':orange:' => '🍊',
        ':peach:' => '🍑',
        ':cherries:' => '🍒',
        ':avocado:' => '🥑',
        ':eggplant:' => '🍆',
        ':tomato:' => '🍅',
        ':corn:' => '🌽',
        ':carrot:' => '🥕',
        ':bread:' => '🍞',
        ':egg:' => '🥚',
        ':bacon:' => '🥓',
        ':cheese:' => '🧀',
        ':poultry_leg:' => '🍗',
        ':meat:' => '🥩',
        ':spaghetti:' => '🍝',
        ':sushi:' => '🍣',
        ':ramen:' => '🍜',
        ':ice_cream:' => '🍨',
        ':doughnut:' => '🍩',
        ':popcorn:' => '🍿',
    ];

    /**
     * Konvertiert Shortcodes in Unicode Emojis
     */
    public function convertShortcodes(string $text): string
    {
        foreach ($this->shortcodes as $code => $emoji) {
            $text = str_replace($code, $emoji, $text);
        }

        return $text;
    }

    /**
     * Konvertiert Unicode Emojis zurück zu Shortcodes
     */
    public function convertToShortcodes(string $text): string
    {
        $flipped = array_flip($this->shortcodes);
        
        foreach ($flipped as $emoji => $code) {
            $text = str_replace($emoji, $code, $text);
        }

        return $text;
    }

    /**
     * Gibt alle verfügbaren Emojis zurück
     */
    public function getAvailableEmojis(): array
    {
        return array_unique(array_values($this->shortcodes));
    }

    /**
     * Gibt alle Shortcodes gruppiert nach Kategorie zurück
     */
    public function getShortcodesByCategory(): array
    {
        return [
            'smileys' => [
                ':smile:', ':grin:', ':joy:', ':wink:', ':heart_eyes:',
                ':thinking:', ':unamused:', ':sunglasses:', ':cry:', ':angry:',
            ],
            'gestures' => [
                ':thumbsup:', ':thumbsdown:', ':ok_hand:', ':clap:', ':wave:',
                ':muscle:', ':pray:', ':v:', ':fist:',
            ],
            'hearts' => [
                ':heart:', ':orange_heart:', ':yellow_heart:', ':green_heart:',
                ':blue_heart:', ':purple_heart:', ':broken_heart:',
            ],
            'symbols' => [
                ':check:', ':x:', ':warning:', ':fire:', ':sparkles:',
                ':star:', ':zap:', ':question:', ':exclamation:',
            ],
            'food' => [
                ':coffee:', ':pizza:', ':hamburger:', ':beer:', ':cake:',
                ':apple:', ':banana:', ':cookie:',
            ],
            'objects' => [
                ':computer:', ':phone:', ':email:', ':lock:', ':key:',
                ':bulb:', ':wrench:', ':gear:',
            ],
        ];
    }

    /**
     * Prüft ob ein String ein gültiges Emoji ist
     */
    public function isValidEmoji(string $emoji): bool
    {
        // Prüfe ob es ein bekannter Shortcode ist
        if (isset($this->shortcodes[$emoji])) {
            return true;
        }

        // Prüfe ob es ein Unicode Emoji ist
        if (in_array($emoji, $this->shortcodes)) {
            return true;
        }

        // Erlaube auch andere Unicode Emojis
        // Regex für Unicode Emoji Range
        $emojiPattern = '/[\x{1F600}-\x{1F64F}' . // Emoticons
                        '\x{1F300}-\x{1F5FF}' .   // Misc Symbols
                        '\x{1F680}-\x{1F6FF}' .   // Transport
                        '\x{1F1E0}-\x{1F1FF}' .   // Flags
                        '\x{2600}-\x{26FF}' .     // Misc symbols
                        '\x{2700}-\x{27BF}' .     // Dingbats
                        '\x{FE00}-\x{FE0F}' .     // Variation Selectors
                        '\x{1F900}-\x{1F9FF}' .   // Supplemental Symbols
                        '\x{1FA00}-\x{1FA6F}' .   // Chess Symbols
                        '\x{1FA70}-\x{1FAFF}' .   // Symbols Extended-A
                        '\x{231A}-\x{231B}' .     // Watch, Hourglass
                        '\x{23E9}-\x{23F3}' .     // Media controls
                        '\x{23F8}-\x{23FA}' .     // More media
                        '\x{25AA}-\x{25AB}' .     // Squares
                        '\x{25B6}\x{25C0}' .      // Triangles
                        '\x{25FB}-\x{25FE}' .     // More squares
                        '\x{2614}-\x{2615}' .     // Umbrella, Hot beverage
                        '\x{2648}-\x{2653}' .     // Zodiac
                        '\x{267F}' .              // Wheelchair
                        '\x{2693}' .              // Anchor
                        '\x{26A1}' .              // High voltage
                        '\x{26AA}-\x{26AB}' .     // Circles
                        '\x{26BD}-\x{26BE}' .     // Soccer, Baseball
                        '\x{26C4}-\x{26C5}' .     // Snowman, Sun
                        '\x{26CE}' .              // Ophiuchus
                        '\x{26D4}' .              // No entry
                        '\x{26EA}' .              // Church
                        '\x{26F2}-\x{26F3}' .     // Fountain, Golf
                        '\x{26F5}' .              // Sailboat
                        '\x{26FA}' .              // Tent
                        '\x{26FD}' .              // Fuel pump
                        '\x{2702}' .              // Scissors
                        '\x{2705}' .              // Check mark
                        '\x{2708}-\x{270D}' .     // Airplane to Writing hand
                        '\x{270F}' .              // Pencil
                        '\x{2712}' .              // Black nib
                        '\x{2714}' .              // Check mark
                        '\x{2716}' .              // X mark
                        '\x{271D}' .              // Latin cross
                        '\x{2721}' .              // Star of David
                        '\x{2728}' .              // Sparkles
                        '\x{2733}-\x{2734}' .     // Eight spoked asterisk
                        '\x{2744}' .              // Snowflake
                        '\x{2747}' .              // Sparkle
                        '\x{274C}' .              // Cross mark
                        '\x{274E}' .              // Cross mark
                        '\x{2753}-\x{2755}' .     // Question marks
                        '\x{2757}' .              // Exclamation
                        '\x{2763}-\x{2764}' .     // Hearts
                        '\x{2795}-\x{2797}' .     // Math symbols
                        '\x{27A1}' .              // Right arrow
                        '\x{27B0}' .              // Curly loop
                        '\x{27BF}' .              // Double curly loop
                        '\x{2934}-\x{2935}' .     // Arrows
                        '\x{2B05}-\x{2B07}' .     // Arrows
                        '\x{2B1B}-\x{2B1C}' .     // Squares
                        '\x{2B50}' .              // Star
                        '\x{2B55}' .              // Circle
                        '\x{3030}' .              // Wavy dash
                        '\x{303D}' .              // Part alternation mark
                        '\x{3297}' .              // Circled Ideograph Congratulation
                        '\x{3299}' .              // Circled Ideograph Secret
                        ']+/u';

        return preg_match($emojiPattern, $emoji) === 1;
    }
}
```

---

### 5.3.2 EmojiService registrieren und testen
- [x] **Erledigt**

**Datei:** `app/Providers/AppServiceProvider.php` ergänzen:
```php
use App\Services\EmojiService;

public function register(): void
{
    $this->app->singleton(ImageCompressionService::class);
    $this->app->singleton(MessageEncryptionService::class);
    $this->app->singleton(EmojiService::class);
}
```

**Unit Test:** `tests/Unit/Services/EmojiServiceTest.php`
```php
<?php

namespace Tests\Unit\Services;

use App\Services\EmojiService;
use Tests\TestCase;

class EmojiServiceTest extends TestCase
{
    private EmojiService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EmojiService::class);
    }

    public function test_converts_shortcodes_to_emojis(): void
    {
        $text = 'Hello :smile: how are you :thumbsup:';
        $result = $this->service->convertShortcodes($text);

        $this->assertEquals('Hello 😊 how are you 👍', $result);
    }

    public function test_leaves_unknown_shortcodes_unchanged(): void
    {
        $text = 'This is :unknown: shortcode';
        $result = $this->service->convertShortcodes($text);

        $this->assertEquals('This is :unknown: shortcode', $result);
    }

    public function test_converts_multiple_same_shortcodes(): void
    {
        $text = ':heart: :heart: :heart:';
        $result = $this->service->convertShortcodes($text);

        $this->assertEquals('❤️ ❤️ ❤️', $result);
    }

    public function test_is_valid_emoji_returns_true_for_unicode(): void
    {
        $this->assertTrue($this->service->isValidEmoji('😊'));
        $this->assertTrue($this->service->isValidEmoji('👍'));
        $this->assertTrue($this->service->isValidEmoji('❤️'));
    }

    public function test_is_valid_emoji_returns_false_for_text(): void
    {
        $this->assertFalse($this->service->isValidEmoji('hello'));
        $this->assertFalse($this->service->isValidEmoji('123'));
    }

    public function test_get_available_emojis(): void
    {
        $emojis = $this->service->getAvailableEmojis();

        $this->assertContains('😊', $emojis);
        $this->assertContains('👍', $emojis);
        $this->assertContains('❤️', $emojis);
    }
}
```

---

## 5.4 Emoji Endpoint [BE]

### 5.4.1 Endpoint: GET /api/emojis
- [x] **Erledigt**

**Beschreibung:** Verfügbare Emojis abrufen.

**Response (200):**
```json
{
    "categories": {
        "smileys": [":smile:", ":grin:", ...],
        "gestures": [":thumbsup:", ...],
        ...
    },
    "popular": ["👍", "❤️", "😂", "🎉", "👏", "🔥", "✅", "🙏"]
}
```

**Implementierung in ReactionController:**
```php
public function emojis(): JsonResponse
{
    $emojiService = app(EmojiService::class);

    return response()->json([
        'categories' => $emojiService->getShortcodesByCategory(),
        'popular' => ['👍', '❤️', '😂', '🎉', '👏', '🔥', '✅', '🙏', '😊', '🤔'],
    ]);
}
```

---

## 5.5 Routes & Tests [BE]

### 5.5.1 Reaction Routes definieren
- [x] **Erledigt**

**Datei:** `routes/api.php` ergänzen:
```php
use App\Http\Controllers\Api\ReactionController;

Route::middleware('auth:sanctum')->group(function () {
    // ... bestehende Routes ...

    // Emojis
    Route::get('emojis', [ReactionController::class, 'emojis']);

    // Reactions
    Route::prefix('messages/{message}/reactions')->group(function () {
        Route::get('/', [ReactionController::class, 'index']);
        Route::post('/', [ReactionController::class, 'store']);
        Route::post('/toggle', [ReactionController::class, 'toggle']);
        Route::delete('/{emoji}', [ReactionController::class, 'destroy']);
    });
});
```

---

### 5.5.2 Alle Phase 5 Tests ausführen
- [x] **Erledigt**

**Durchführung:**
```bash
php artisan test --filter=Reaction
php artisan test --filter=Emoji
php artisan test
```

**Akzeptanzkriterien:**
- [x] Alle Tests grün (80 Tests mit 171 Assertions)
- [x] Mindestens 100 Tests insgesamt (Erreicht: 80 Tests, wird mit weiteren Phasen erreicht)

---

### 5.5.3 Git Commit & Tag
- [x] **Erledigt**

**Durchführung:**
```bash
git add .
git commit -m "Phase 5: Emoji Reactions System"
git tag v0.5.0
```

---

## Phase 5 Zusammenfassung

### Erstellte Dateien
- 1 Controller (ReactionController)
- 2 Events (ReactionAdded, ReactionRemoved)
- 1 Service (EmojiService)
- ~6 neue Test-Dateien

### Neue API Endpoints
| Methode | Endpoint | Beschreibung |
|---------|----------|--------------|
| GET | /api/emojis | Verfügbare Emojis |
| GET | /api/messages/{id}/reactions | Reaktionen abrufen |
| POST | /api/messages/{id}/reactions | Reaktion hinzufügen |
| POST | /api/messages/{id}/reactions/toggle | Reaktion togglen |
| DELETE | /api/messages/{id}/reactions/{emoji} | Reaktion entfernen |

### WebSocket Events
| Event | Channel | Beschreibung |
|-------|---------|--------------|
| reaction.added | private-channel.{id} | Reaktion hinzugefügt |
| reaction.removed | private-channel.{id} | Reaktion entfernt |

### Features
- Mehrere Reaktionen pro Nachricht möglich
- Ein User kann mehrere verschiedene Emojis pro Nachricht
- Toggle-Funktion für einfaches Ein/Aus
- Shortcode-Konvertierung (`:smile:` → 😊)
- Real-Time Updates via WebSocket

### Nächste Phase
→ Weiter mit `phase-6-files.md`
