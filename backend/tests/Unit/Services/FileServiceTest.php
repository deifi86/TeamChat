<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Models\Channel;
use App\Models\DirectConversation;
use App\Models\File;
use App\Services\FileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FileServiceTest extends TestCase
{
    use RefreshDatabase;

    private FileService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->service = app(FileService::class);
    }

    public function test_uploads_file_to_channel_path(): void
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->create();

        $uploadedFile = UploadedFile::fake()->create('document.pdf', 100);

        $file = $this->service->upload(
            $uploadedFile,
            $user,
            'channel',
            $channel->id
        );

        $this->assertStringContainsString("files/channels/{$channel->id}", $file->file_path);
        $this->assertEquals('document.pdf', $file->original_name);
        $this->assertEquals($user->id, $file->uploader_id);
        Storage::disk('public')->assertExists($file->file_path);
    }

    public function test_uploads_file_to_conversation_path(): void
    {
        $user = User::factory()->create();
        $conversation = DirectConversation::factory()->create();

        $uploadedFile = UploadedFile::fake()->create('doc.pdf', 100);

        $file = $this->service->upload(
            $uploadedFile,
            $user,
            'direct',
            $conversation->id
        );

        $this->assertStringContainsString("files/conversations/{$conversation->id}", $file->file_path);
    }

    public function test_creates_thumbnail_for_images(): void
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->create();

        $uploadedFile = UploadedFile::fake()->image('photo.jpg', 800, 600);

        $file = $this->service->upload(
            $uploadedFile,
            $user,
            'channel',
            $channel->id
        );

        $this->assertNotNull($file->thumbnail_path);
        Storage::disk('public')->assertExists($file->thumbnail_path);
    }

    public function test_does_not_create_thumbnail_for_non_images(): void
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->create();

        $uploadedFile = UploadedFile::fake()->create('document.pdf', 100);

        $file = $this->service->upload(
            $uploadedFile,
            $user,
            'channel',
            $channel->id
        );

        $this->assertNull($file->thumbnail_path);
    }

    public function test_delete_removes_file_and_thumbnail(): void
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->create();

        $uploadedFile = UploadedFile::fake()->image('photo.jpg', 400, 300);

        $file = $this->service->upload(
            $uploadedFile,
            $user,
            'channel',
            $channel->id
        );

        $filePath = $file->file_path;
        $thumbnailPath = $file->thumbnail_path;

        $this->service->delete($file);

        Storage::disk('public')->assertMissing($filePath);
        if ($thumbnailPath) {
            Storage::disk('public')->assertMissing($thumbnailPath);
        }
        $this->assertDatabaseMissing('files', ['id' => $file->id]);
    }

    public function test_can_access_returns_true_for_channel_member(): void
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->create();
        $channel->users()->attach($user->id);

        $file = File::factory()->create([
            'fileable_type' => 'channel',
            'fileable_id' => $channel->id,
        ]);

        $this->assertTrue($this->service->canAccess($user, $file));
    }

    public function test_can_access_returns_false_for_non_member(): void
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->create();

        $file = File::factory()->create([
            'fileable_type' => 'channel',
            'fileable_id' => $channel->id,
        ]);

        $this->assertFalse($this->service->canAccess($user, $file));
    }

    public function test_can_access_returns_true_for_conversation_participant(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $conversation = DirectConversation::findOrCreateBetween($user, $other);

        $file = File::factory()->create([
            'fileable_type' => 'direct',
            'fileable_id' => $conversation->id,
        ]);

        $this->assertTrue($this->service->canAccess($user, $file));
    }
}
