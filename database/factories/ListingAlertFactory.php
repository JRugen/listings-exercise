<?php

namespace Database\Factories;

use App\Models\Listing;
use App\Models\ListingAlert;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ListingAlert>
 */
class ListingAlertFactory extends Factory
{
    protected $model = ListingAlert::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'listing_id' => Listing::factory(),
            'user_id' => User::factory(),
        ];
    }
}
