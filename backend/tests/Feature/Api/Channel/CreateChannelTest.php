<?php

namespace Tests\Feature\Api\Channel;

use App\Models\User;
use App\Models\Company;
use App\Models\Channel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateChannelTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_channel(): void
    {
        $admin = User::factory()->create();
        $company = Company::factory()->create();
        $company->users()->attach($admin->id, ['role' => 'admin']);

        $response = $this->actingAs($admin)
            ->postJson("/api/companies/{$company->id}/channels", [
                'name' => 'Neuer Channel',
                'is_private' => true,
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('channels', [
            'company_id' => $company->id,
            'name' => 'Neuer Channel',
        ]);
    }

    public function test_user_cannot_create_channel(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $company->users()->attach($user->id, ['role' => 'user']);

        $response = $this->actingAs($user)
            ->postJson("/api/companies/{$company->id}/channels", [
                'name' => 'Test Channel',
            ]);

        $response->assertStatus(403);
    }

    public function test_public_channel_includes_all_members(): void
    {
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $company = Company::factory()->create();

        $company->users()->attach($admin->id, ['role' => 'admin']);
        $company->users()->attach($member->id, ['role' => 'user']);

        $this->actingAs($admin)
            ->postJson("/api/companies/{$company->id}/channels", [
                'name' => 'Public Channel',
                'is_private' => false,
            ]);

        $channel = Channel::where('name', 'Public Channel')->first();

        $this->assertTrue($admin->isMemberOfChannel($channel));
        $this->assertTrue($member->isMemberOfChannel($channel));
    }

    public function test_private_channel_only_includes_creator(): void
    {
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $company = Company::factory()->create();

        $company->users()->attach($admin->id, ['role' => 'admin']);
        $company->users()->attach($member->id, ['role' => 'user']);

        $this->actingAs($admin)
            ->postJson("/api/companies/{$company->id}/channels", [
                'name' => 'Private Channel',
                'is_private' => true,
            ]);

        $channel = Channel::where('name', 'Private Channel')->first();

        $this->assertTrue($admin->isMemberOfChannel($channel));
        $this->assertFalse($member->isMemberOfChannel($channel));
    }
}
