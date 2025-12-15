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
