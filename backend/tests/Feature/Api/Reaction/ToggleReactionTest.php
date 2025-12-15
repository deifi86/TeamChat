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
        $channel->users()->attach($user->id);

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
