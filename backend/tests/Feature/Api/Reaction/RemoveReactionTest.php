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
        $channel->users()->attach($user->id);

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
        $channel->users()->attach([$user->id, $other->id]);

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
        $channel->users()->attach($user->id);

        $message = Message::factory()->create([
            'messageable_type' => 'channel',
            'messageable_id' => $channel->id,
        ]);

        $response = $this->actingAs($user)
            ->deleteJson("/api/messages/{$message->id}/reactions/👍");

        $response->assertStatus(404);
    }
}
