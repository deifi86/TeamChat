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
        $channel->users()->attach([$user1->id, $user2->id]);

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
        $channel->users()->attach([$user->id, $other->id]);

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
