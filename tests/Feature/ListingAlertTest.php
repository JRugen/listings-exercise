<?php

namespace Tests\Feature;

use App\Models\ListingAlert;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListingAlertTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_only_be_alerted_once_per_listing(): void
    {
        $alert = ListingAlert::factory()->create();

        $this->expectException(QueryException::class);

        ListingAlert::create([
            'listing_id' => $alert->listing_id,
            'user_id' => $alert->user_id,
        ]);
    }
}
