<?php

namespace Tests\Feature\Api\File;

use App\Models\User;
use App\Models\Company;
use App\Models\Channel;
use App\Models\File;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DeleteFileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_uploader_can_delete_own_file(): void
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->create();
        $channel->users()->attach($user->id);

        Storage::disk('public')->put('files/test.pdf', 'content');

        $file = File::factory()->create([
            'fileable_type' => 'channel',
            'fileable_id' => $channel->id,
            'uploader_id' => $user->id,
            'file_path' => 'files/test.pdf',
        ]);

        $response = $this->actingAs($user)
            ->deleteJson("/api/files/{$file->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('files', ['id' => $file->id]);
    }

    public function test_admin_can_delete_any_file_in_channel(): void
    {
        $admin = User::factory()->create();
        $uploader = User::factory()->create();
        $company = Company::factory()->create();
        $company->users()->attach($admin->id, ['role' => 'admin']);

        $channel = Channel::factory()->create(['company_id' => $company->id]);

        Storage::disk('public')->put('files/test.pdf', 'content');

        $file = File::factory()->create([
            'fileable_type' => 'channel',
            'fileable_id' => $channel->id,
            'uploader_id' => $uploader->id,
            'file_path' => 'files/test.pdf',
        ]);

        $response = $this->actingAs($admin)
            ->deleteJson("/api/files/{$file->id}");

        $response->assertOk();
    }

    public function test_other_user_cannot_delete_file(): void
    {
        $user = User::factory()->create();
        $uploader = User::factory()->create();
        $channel = Channel::factory()->create();
        $channel->users()->attach([$user->id, $uploader->id]);

        $file = File::factory()->create([
            'fileable_type' => 'channel',
            'fileable_id' => $channel->id,
            'uploader_id' => $uploader->id,
        ]);

        $response = $this->actingAs($user)
            ->deleteJson("/api/files/{$file->id}");

        $response->assertStatus(403);
    }
}
