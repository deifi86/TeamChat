<?php

namespace Tests\Feature\Api\File;

use App\Models\User;
use App\Models\Channel;
use App\Models\File;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ListFilesTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_list_channel_files(): void
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->create();
        $channel->users()->attach($user->id);

        File::factory()->count(3)->create([
            'fileable_type' => 'channel',
            'fileable_id' => $channel->id,
            'uploader_id' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/channels/{$channel->id}/files");

        $response->assertOk()
            ->assertJsonCount(3, 'files')
            ->assertJsonStructure([
                'files' => [
                    '*' => ['id', 'original_name', 'mime_type', 'url'],
                ],
                'pagination',
            ]);
    }

    public function test_can_filter_by_type(): void
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->create();
        $channel->users()->attach($user->id);

        // Bilder
        File::factory()->count(2)->create([
            'fileable_type' => 'channel',
            'fileable_id' => $channel->id,
            'mime_type' => 'image/jpeg',
        ]);

        // PDFs
        File::factory()->create([
            'fileable_type' => 'channel',
            'fileable_id' => $channel->id,
            'mime_type' => 'application/pdf',
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/channels/{$channel->id}/files?type=image");

        $response->assertOk()
            ->assertJsonCount(2, 'files');
    }

    public function test_can_search_by_name(): void
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->create();
        $channel->users()->attach($user->id);

        File::factory()->create([
            'fileable_type' => 'channel',
            'fileable_id' => $channel->id,
            'original_name' => 'important-report.pdf',
        ]);

        File::factory()->create([
            'fileable_type' => 'channel',
            'fileable_id' => $channel->id,
            'original_name' => 'random.jpg',
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/channels/{$channel->id}/files?search=report");

        $response->assertOk()
            ->assertJsonCount(1, 'files')
            ->assertJsonFragment(['original_name' => 'important-report.pdf']);
    }

    public function test_pagination_works(): void
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->create();
        $channel->users()->attach($user->id);

        File::factory()->count(25)->create([
            'fileable_type' => 'channel',
            'fileable_id' => $channel->id,
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/channels/{$channel->id}/files?per_page=10");

        $response->assertOk()
            ->assertJsonCount(10, 'files')
            ->assertJsonPath('pagination.total', 25)
            ->assertJsonPath('pagination.last_page', 3);
    }

    public function test_non_member_cannot_list_files(): void
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->create();

        $response = $this->actingAs($user)
            ->getJson("/api/channels/{$channel->id}/files");

        $response->assertStatus(403);
    }
}
