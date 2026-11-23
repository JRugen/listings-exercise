<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\ListingAlert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AlertsPageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The auth stub resolves every request as the first user, so creating
     * them first makes them the signed-in user.
     */
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_it_lists_only_the_users_own_alerts(): void
    {
        ListingAlert::factory(2)->for($this->user)
            ->state(fn () => ['listing_id' => Listing::factory()->live()])
            ->create();
        ListingAlert::factory()->for(Listing::factory()->live())->create(); // someone else's

        $this->get('/alerts')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Alerts/Index')
                ->has('alerts.data', 2)
            );
    }

    public function test_newest_alerts_come_first_with_their_listing(): void
    {
        $older = ListingAlert::factory()->for($this->user)->for(Listing::factory()->live())
            ->create(['created_at' => now()->subDay()]);
        $newer = ListingAlert::factory()->for($this->user)->for(Listing::factory()->live())
            ->create(['created_at' => now()]);

        $this->get('/alerts')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('alerts.data.0.id', $newer->id)
                ->where('alerts.data.0.listing.id', $newer->listing_id)
                ->where('alerts.data.1.id', $older->id)
            );
    }

    public function test_alerts_for_listings_no_longer_live_are_left_out(): void
    {
        ListingAlert::factory()->for($this->user)->for(Listing::factory()->live())->create();
        ListingAlert::factory()->for($this->user)->for(Listing::factory()->sold())->create();
        ListingAlert::factory()->for($this->user)->for(Listing::factory()->underOffer())->create();

        $this->get('/alerts')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->has('alerts.data', 1));
    }
}
