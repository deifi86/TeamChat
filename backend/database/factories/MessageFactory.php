<?php

namespace Database\Factories;

use App\Models\Channel;
use App\Models\User;
use App\Services\MessageEncryptionService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Message>
 */
class MessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $encryptionService = app(MessageEncryptionService::class);
        $content = fake()->sentence();
        $encrypted = $encryptionService->encryptForStorage($content);

        return [
            'messageable_type' => 'channel',
            'messageable_id' => Channel::factory(),
            'sender_id' => User::factory(),
            'content' => $encrypted['content'],
            'content_iv' => $encrypted['content_iv'],
            'content_type' => 'text',
            'parent_id' => null,
        ];
    }
}
