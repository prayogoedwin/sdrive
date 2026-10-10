<?php

namespace Database\Factories;

use App\Enums\DriveItemType;
use App\Models\DriveItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DriveItem>
 */
class DriveItemFactory extends Factory
{
    protected $model = DriveItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'type' => DriveItemType::Folder,
            'owner_id' => User::factory(),
            'created_by' => fn (array $attributes): int => (int) $attributes['owner_id'],
            'size' => 0,
        ];
    }
}
