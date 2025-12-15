<?php

namespace Tests\Feature\Api\Message;

use App\Events\NewMessage;
use App\Models\User;
use App\Models\Channel;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class SendChannelMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_send_message(): void
    {
        Event::fake([NewMessage::class]);

        $user = User::factory()->create();
        $channel = Channel::factory()->create();
        $channel->users()->attach($user->id);

        $response = $this->actingAs($user)
            ->postJson("/api/channels/{$channel->id}/messages", [
                'content' => 'Hello, World!',
            ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['content' => 'Hello, World!']);

        $this->assertDatabaseHas('messages', [
            'messageable_type' => 'channel',
            'messageable_id' => $channel->id,
            'sender_id' => $user->id,
        ]);

        Event::assertDispatched(NewMessage::class);
    }

    public function test_non_member_cannot_send_message(): void
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->create();

        $response = $this->actingAs($user)
            ->postJson("/api/channels/{$channel->id}/messages", [
                'content' => 'Hello!',
            ]);

        $response->assertStatus(403);
    }

    public function test_message_is_encrypted_in_database(): void
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->create();
        $channel->users()->attach($user->id);

        $this->actingAs($user)
            ->postJson("/api/channels/{$channel->id}/messages", [
                'content' => 'Secret message',
            ]);

        $message = Message::first();

        // Content in DB sollte nicht der Klartext sein
        $this->assertNotEquals('Secret message', $message->content);
        $this->assertNotNull($message->content_iv);
    }

    public function test_can_reply_to_message(): void
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->create();
        $channel->users()->attach($user->id);

        $parentMessage = Message::factory()->create([
            'messageable_type' => 'channel',
            'messageable_id' => $channel->id,
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/channels/{$channel->id}/messages", [
                'content' => 'This is a reply',
                'parent_id' => $parentMessage->id,
            ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['parent_id' => $parentMessage->id]);
    }

    public function test_cannot_reply_to_message_from_different_channel(): void
    {
        $user = User::factory()->create();
        $channel1 = Channel::factory()->create();
        $channel2 = Channel::factory()->create();
        $channel1->users()->attach($user->id);

        $parentMessage = Message::factory()->create([
            'messageable_type' => 'channel',
            'messageable_id' => $channel2->id,
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/channels/{$channel1->id}/messages", [
                'content' => 'Invalid reply',
                'parent_id' => $parentMessage->id,
            ]);

        $response->assertStatus(422);
    }
}
