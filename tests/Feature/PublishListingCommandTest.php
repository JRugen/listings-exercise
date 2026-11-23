<?php

namespace Tests\Feature;

use App\Enums\ListingStatus;
use App\Events\ListingWentLive;
use App\Models\Listing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class PublishListingCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([ListingWentLive::class]);
    }

    public function test_it_publishes_a_listing_by_reference(): void
    {
        $listing = Listing::factory()->draft()->create();

        $this->artisan('listing:publish', ['reference' => $listing->reference])
            ->expectsOutput("{$listing->reference} is now live.")
            ->assertSuccessful();

        $this->assertSame(ListingStatus::Live, $listing->fresh()->status);
        Event::assertDispatched(ListingWentLive::class);
    }

    public function test_it_reports_a_listing_that_is_already_live(): void
    {
        $listing = Listing::factory()->live()->create();

        $this->artisan('listing:publish', ['reference' => $listing->reference])
            ->expectsOutput("{$listing->reference} is already live.")
            ->assertSuccessful();

        Event::assertNotDispatched(ListingWentLive::class);
    }

    public function test_it_fails_for_an_unknown_reference(): void
    {
        $this->artisan('listing:publish', ['reference' => 'STR-000000'])
            ->expectsOutput('No listing found with reference STR-000000.')
            ->assertFailed();
    }
}
