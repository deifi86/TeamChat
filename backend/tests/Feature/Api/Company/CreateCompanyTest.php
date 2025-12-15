<?php

namespace Tests\Feature\Api\Company;

use App\Models\User;
use App\Models\Company;
use App\Models\Channel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateCompanyTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_company(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/companies', [
                'name' => 'Neue Firma GmbH',
                'join_password' => 'secret123',
            ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'Neue Firma GmbH']);

        $this->assertDatabaseHas('companies', [
            'name' => 'Neue Firma GmbH',
            'owner_id' => $user->id,
        ]);
    }

    public function test_slug_is_auto_generated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/companies', [
                'name' => 'Test Firma GmbH',
                'join_password' => 'secret123',
            ]);

        $this->assertDatabaseHas('companies', [
            'slug' => 'test-firma-gmbh',
        ]);
    }

    public function test_creates_default_channel(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/companies', [
                'name' => 'Test Firma',
                'join_password' => 'secret123',
            ]);

        $company = Company::where('name', 'Test Firma')->first();

        $this->assertDatabaseHas('channels', [
            'company_id' => $company->id,
            'name' => 'Allgemein',
            'is_private' => false,
        ]);
    }

    public function test_creator_is_admin(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/companies', [
                'name' => 'Test Firma',
                'join_password' => 'secret123',
            ]);

        $company = Company::where('name', 'Test Firma')->first();

        $this->assertTrue($user->isAdminOf($company));
    }

    public function test_creator_is_in_default_channel(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/companies', [
                'name' => 'Test Firma',
                'join_password' => 'secret123',
            ]);

        $company = Company::where('name', 'Test Firma')->first();
        $channel = $company->channels()->first();

        $this->assertTrue($user->isMemberOfChannel($channel));
    }

    public function test_password_is_hashed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/companies', [
                'name' => 'Test Firma',
                'join_password' => 'secret123',
            ]);

        $company = Company::where('name', 'Test Firma')->first();

        $this->assertNotEquals('secret123', $company->join_password);
        $this->assertTrue($company->checkJoinPassword('secret123'));
    }

    public function test_validation_fails_without_name(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/companies', [
                'join_password' => 'secret123',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_validation_fails_with_short_password(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/companies', [
                'name' => 'Test Firma',
                'join_password' => '12345',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['join_password']);
    }
}
