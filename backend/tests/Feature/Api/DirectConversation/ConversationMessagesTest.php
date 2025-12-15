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
            'sender_id' => $other->id,
            'content' => $encrypted['content'],
            'content_iv' => $encrypted['content_iv'],
            'created_at' => now(),
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
