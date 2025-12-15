<?php

namespace Tests\Feature\Broadcasting;

use App\Models\User;
use App\Models\Channel;
use App\Models\Company;
use App\Models\DirectConversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChannelAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_subscribe_to_own_user_channel(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->post('/broadcasting/auth', [
                'channel_name' => 'private-user.' . $user->id,
            ]);

        $response->assertOk();
    }

    public function test_user_cannot_subscribe_to_other_user_channel(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $response = $this->actingAs($user)
            ->post('/broadcasting/auth', [
                'channel_name' => 'private-user.' . $other->id,
            ]);

        // Broadcasting Auth sollte fehlschlagen - prüfe dass Response leer ist oder 403
        $this->assertTrue(
            $response->status() === 403 || empty($response->getContent()),
            'Expected 403 or empty response for unauthorized channel access'
        );
    }

    public function test_member_can_subscribe_to_channel(): void
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->create();
        $channel->users()->attach($user->id);

        $response = $this->actingAs($user)
            ->post('/broadcasting/auth', [
                'channel_name' => 'private-channel.' . $channel->id,
            ]);

        $response->assertOk();
    }

    public function test_non_member_cannot_subscribe_to_channel(): void
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->create();

        $response = $this->actingAs($user)
            ->post('/broadcasting/auth', [
                'channel_name' => 'private-channel.' . $channel->id,
            ]);

        // Broadcasting Auth sollte fehlschlagen - prüfe dass Response leer ist oder 403
        $this->assertTrue(
            $response->status() === 403 || empty($response->getContent()),
            'Expected 403 or empty response for unauthorized channel access'
        );
    }

    public function test_participant_can_subscribe_to_conversation(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $conversation = DirectConversation::findOrCreateBetween($userA, $userB);

        $response = $this->actingAs($userA)
            ->post('/broadcasting/auth', [
                'channel_name' => 'private-conversation.' . $conversation->id,
            ]);

        $response->assertOk();
    }

    public function test_non_participant_cannot_subscribe_to_conversation(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $userC = User::factory()->create();
        $conversation = DirectConversation::findOrCreateBetween($userA, $userB);

        $response = $this->actingAs($userC)
            ->post('/broadcasting/auth', [
                'channel_name' => 'private-conversation.' . $conversation->id,
            ]);

        // Broadcasting Auth sollte fehlschlagen - prüfe dass Response leer ist oder 403
        $this->assertTrue(
            $response->status() === 403 || empty($response->getContent()),
            'Expected 403 or empty response for unauthorized channel access'
        );
    }

    public function test_company_member_can_subscribe_to_presence_channel(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $company->users()->attach($user->id, ['role' => 'user']);

        $response = $this->actingAs($user)
            ->post('/broadcasting/auth', [
                'channel_name' => 'presence-company.' . $company->id,
            ]);

        $response->assertOk();
    }
}
