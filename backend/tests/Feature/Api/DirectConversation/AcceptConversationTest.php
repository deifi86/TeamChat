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
