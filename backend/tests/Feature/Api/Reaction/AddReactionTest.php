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
        $channel->users()->attach($user->id);

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
        $channel->users()->attach($user->id);

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
        $channel->users()->attach($user->id);

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
