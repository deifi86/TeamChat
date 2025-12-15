<?php

namespace Database\Factories;

use App\Models\Channel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class FileFactory extends Factory
{
    public function definition(): array
    {
        $mimeTypes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'application/pdf' => 'pdf',
            'text/plain' => 'txt',
        ];

        $mimeType = fake()->randomElement(array_keys($mimeTypes));
        $extension = $mimeTypes[$mimeType];
        $storedName = Str::uuid() . '.' . $extension;

        return [
            'message_id' => null,
            'uploader_id' => User::factory(),
            'fileable_type' => 'channel',
            'fileable_id' => Channel::factory(),
            'original_name' => fake()->word() . '.' . $extension,
            'stored_name' => $storedName,
            'mime_type' => $mimeType,
            'file_size' => fake()->numberBetween(1000, 5000000),
            'file_path' => 'files/' . $storedName,
            'thumbnail_path' => null,
            'is_compressed' => false,
            'original_size' => null,
        ];
    }

    public function image(): static
    {
        return $this->state(fn (array $attributes) => [
            'mime_type' => 'image/jpeg',
            'original_name' => fake()->word() . '.jpg',
            'thumbnail_path' => 'files/thumbnails/thumb_' . Str::uuid() . '.jpg',
        ]);
    }

    public function pdf(): static
    {
        return $this->state(fn (array $attributes) => [
            'mime_type' => 'application/pdf',
            'original_name' => fake()->word() . '.pdf',
        ]);
    }

    public function compressed(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_compressed' => true,
            'original_size' => fake()->numberBetween(2000000, 10000000),
        ]);
    }
}
