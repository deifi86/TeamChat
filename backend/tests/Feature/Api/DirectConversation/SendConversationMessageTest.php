<?php

namespace Tests\Feature\Api\DirectConversation;

use App\Models\User;
use App\Models\DirectConversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SendConversationMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_participant_can_send_message(): void
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
