<?php

namespace Tests\Feature;

use App\Models\Listing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListUnpublishedListingsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_every_listing_that_is_not_live(): void
    {
        $draft = Listing::factory()->draft()->create();
        $underOffer = Listing::factory()->underOffer()->create();
        $sold = Listing::factory()->sold()->create();
        $live = Listing::factory()->live()->create();

        $this->artisan('listing:unpublished')
            ->expectsOutputToContain($draft->reference)
            ->expectsOutputToContain($underOffer->reference)
            ->expectsOutputToContain($sold->reference)
            ->doesntExpectOutputToContain($live->reference)
            ->assertSuccessful();
    }
}
