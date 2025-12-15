<?php

namespace Tests\Feature\Api\DirectConversation;

use App\Models\User;
use App\Models\DirectConversation;
use App\Models\Message;
use App\Services\MessageEncryptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListConversationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_own_conversations(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        DirectConversation::findOrCreateBetween($user, $other);

        $response = $this->actingAs($user)
            ->getJson('/api/conversations');

        $response->assertOk()
            ->assertJsonCount(1, 'conversations')
            ->assertJsonStructure([
                'conversations' => [
                    '*' => ['id', 'other_user', 'is_accepted', 'last_message']
                ]
            ]);
    }

    public function test_conversations_show_other_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create(['username' => 'OtherUser']);

        DirectConversation::findOrCreateBetween($user, $other);

        $response = $this->actingAs($user)
            ->getJson('/api/conversations');

        $response->assertOk()
            ->assertJsonFragment(['username' => 'OtherUser']);
    }

    public function test_conversations_show_last_message(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $conversation = DirectConversation::findOrCreateBetween($user, $other);
        $conversation->update([
            'user_one_accepted' => true,
            'user_two_accepted' => true,
        ]);

        $encryptionService = app(MessageEncryptionService::class);
        $encrypted = $encryptionService->encryptForStorage('Hello there!');

        Message::factory()->create([
            'messageable_type' => 'direct',
            'messageable_id' => $conversation->id,
            'sender_id' => $other->id,
            'content' => $encrypted['content'],
            'content_iv' => $encrypted['content_iv'],
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/conversations');

        $response->assertOk()
            ->assertJsonFragment(['content' => 'Hello there!']);
    }

    public function test_does_not_show_others_conversations(): void
    {
        $user = User::factory()->create();
        $other1 = User::factory()->create();
        $other2 = User::factory()->create();

        // Conversation zwischen anderen Usern
        DirectConversation::findOrCreateBetween($other1, $other2);

        $response = $this->actingAs($user)
            ->getJson('/api/conversations');

        $response->assertOk()
            ->assertJsonCount(0, 'conversations');
    }
}
