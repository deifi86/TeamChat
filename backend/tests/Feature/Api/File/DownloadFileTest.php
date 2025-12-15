<?php

namespace Tests\Feature\Api\File;

use App\Models\User;
use App\Models\Channel;
use App\Models\File;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DownloadFileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_member_can_download_file(): void
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->create();
        $channel->users()->attach($user->id);

        // Echte Datei erstellen
        Storage::disk('public')->put('files/test.pdf', 'PDF content');

        $file = File::factory()->create([
            'fileable_type' => 'channel',
            'fileable_id' => $channel->id,
            'file_path' => 'files/test.pdf',
            'original_name' => 'document.pdf',
        ]);

        $response = $this->actingAs($user)
            ->get("/api/files/{$file->id}/download");

        $response->assertOk();
        $response->assertHeader('content-disposition', 'attachment; filename=document.pdf');
    }

    public function test_non_member_cannot_download(): void
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->create();

        $file = File::factory()->create([
            'fileable_type' => 'channel',
            'fileable_id' => $channel->id,
        ]);

        $response = $this->actingAs($user)
            ->get("/api/files/{$file->id}/download");

        $response->assertStatus(404);
    }
}
