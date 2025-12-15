<?php

namespace Tests\Feature\Api\Company;

use App\Models\User;
use App\Models\Company;
use App\Models\Channel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class JoinCompanyTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_join_with_correct_password(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create([
            'join_password' => Hash::make('secret123'),
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/companies/{$company->id}/join", [
                'password' => 'secret123',
            ]);

        $response->assertOk();
        $this->assertTrue($user->isMemberOf($company));
    }

    public function test_join_fails_with_wrong_password(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create([
            'join_password' => Hash::make('secret123'),
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/companies/{$company->id}/join", [
                'password' => 'wrongpassword',
            ]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'Invalid password']);
    }

    public function test_already_member_gets_error(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create([
            'join_password' => Hash::make('secret123'),
        ]);
        $company->users()->attach($user->id);

        $response = $this->actingAs($user)
            ->postJson("/api/companies/{$company->id}/join", [
                'password' => 'secret123',
            ]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'You are already a member of this company']);
    }

    public function test_join_adds_to_public_channels(): void
    {
        $user = User::factory()->create();
        $owner = User::factory()->create();
        $company = Company::factory()->create([
            'owner_id' => $owner->id,
            'join_password' => Hash::make('secret123'),
        ]);

        $publicChannel = Channel::factory()->create([
            'company_id' => $company->id,
            'is_private' => false,
        ]);
        $privateChannel = Channel::factory()->create([
            'company_id' => $company->id,
            'is_private' => true,
        ]);

        $this->actingAs($user)
            ->postJson("/api/companies/{$company->id}/join", [
                'password' => 'secret123',
            ]);

        $this->assertTrue($user->isMemberOfChannel($publicChannel));
        $this->assertFalse($user->isMemberOfChannel($privateChannel));
    }
}
