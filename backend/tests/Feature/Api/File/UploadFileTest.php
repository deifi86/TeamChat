<?php

namespace Tests\Feature\Api\File;

use App\Events\NewMessage;
use App\Models\User;
use App\Models\Channel;
use App\Models\DirectConversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UploadFileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_member_can_upload_file_to_channel(): void
    {
        Event::fake([NewMessage::class]);

        $user = User::factory()->create();
        $channel = Channel::factory()->create();
        $channel->users()->attach($user->id);

        $file = UploadedFile::fake()->create('document.pdf', 500);

        $response = $this->actingAs($user)
            ->postJson("/api/channels/{$channel->id}/files", [
                'file' => $file,
                'message' => 'Check this document',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'file' => ['id', 'original_name', 'url'],
                    'message' => ['id', 'sender'],
                ],
            ]);

        $this->assertDatabaseHas('files', [
            'original_name' => 'document.pdf',
            'fileable_type' => 'channel',
            'fileable_id' => $channel->id,
        ]);

        Event::assertDispatched(NewMessage::class);
    }

    public function test_non_member_cannot_upload_to_channel(): void
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->create();

        $file = UploadedFile::fake()->create('document.pdf', 500);

        $response = $this->actingAs($user)
            ->postJson("/api/channels/{$channel->id}/files", [
                'file' => $file,
            ]);

        $response->assertStatus(403);
    }

    public function test_uploads_image_with_thumbnail(): void
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->create();
        $channel->users()->attach($user->id);

        $file = UploadedFile::fake()->image('photo.jpg', 800, 600);

        $response = $this->actingAs($user)
            ->postJson("/api/channels/{$channel->id}/files", [
                'file' => $file,
            ]);

        $response->assertStatus(201);

        $fileData = $response->json('data.file');
        $this->assertNotNull($fileData['thumbnail_url']);
        $this->assertTrue($fileData['is_image']);
    }

    public function test_validates_file_type(): void
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->create();
        $channel->users()->attach($user->id);

        // Gefährliche Datei
        $file = UploadedFile::fake()->create('virus.exe', 100, 'application/x-msdownload');

        $response = $this->actingAs($user)
            ->postJson("/api/channels/{$channel->id}/files", [
                'file' => $file,
            ]);

        $response->assertStatus(422);
    }

    public function test_participant_can_upload_to_conversation(): void
    {
        Event::fake([NewMessage::class]);

        $user = User::factory()->create();
        $other = User::factory()->create();

        $conversation = DirectConversation::factory()->create([
            'user_one_id' => min($user->id, $other->id),
            'user_two_id' => max($user->id, $other->id),
            'user_one_accepted' => true,
            'user_two_accepted' => true,
        ]);

        $file = UploadedFile::fake()->create('document.pdf', 500);

        $response = $this->actingAs($user)
            ->postJson("/api/conversations/{$conversation->id}/files", [
                'file' => $file,
            ]);

        $response->assertStatus(201);
    }

    public function test_cannot_upload_to_unaccepted_conversation(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $conversation = DirectConversation::findOrCreateBetween($user, $other);

        $file = UploadedFile::fake()->create('document.pdf', 500);

        $response = $this->actingAs($other)
            ->postJson("/api/conversations/{$conversation->id}/files", [
                'file' => $file,
            ]);

        $response->assertStatus(403);
    }
}
