<?php

namespace Database\Factories;

use App\Models\DirectConversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DirectConversationFactory extends Factory
{
    protected $model = DirectConversation::class;

    public function definition(): array
    {
        $userOne = User::factory()->create();
        $userTwo = User::factory()->create();

        return [
            'user_one_id' => min($userOne->id, $userTwo->id),
            'user_two_id' => max($userOne->id, $userTwo->id),
            'user_one_accepted' => false,
            'user_two_accepted' => false,
        ];
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_one_accepted' => true,
            'user_two_accepted' => true,
        ]);
    }
}
