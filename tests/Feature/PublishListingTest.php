<?php

namespace Tests\Feature;

use App\Actions\PublishListing;
use App\Enums\ListingStatus;
use App\Events\ListingWentLive;
use App\Models\Listing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class PublishListingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([ListingWentLive::class]);
    }

    #[DataProvider('nonLiveStates')]
    public function test_a_listing_goes_live_from_any_other_status(string $state): void
    {
        $listing = Listing::factory()->{$state}()->create();

        $this->assertTrue(app(PublishListing::class)->handle($listing));

        $this->assertSame(ListingStatus::Live, $listing->status);
        $this->assertSame(ListingStatus::Live, $listing->fresh()->status);
        Event::assertDispatchedTimes(ListingWentLive::class, 1);
        Event::assertDispatched(ListingWentLive::class, fn (ListingWentLive $event) => $event->listing->is($listing));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nonLiveStates(): array
    {
        return [
            'draft' => ['draft'],
            'under offer' => ['underOffer'],
            'sold' => ['sold'],
        ];
    }

    public function test_publishing_a_live_listing_changes_nothing_and_fires_nothing(): void
    {
        $listing = Listing::factory()->live()->create();
        $before = $listing->fresh();

        $this->assertFalse(app(PublishListing::class)->handle($listing));

        $this->assertEquals($before->listed_at, $listing->fresh()->listed_at);
        $this->assertEquals($before->updated_at, $listing->fresh()->updated_at);
        Event::assertNotDispatched(ListingWentLive::class);
    }

    /**
     * Two publishes racing on the same listing each hold a copy that says
     * "draft". Only the first may go live and fire the event.
     */
    public function test_a_stale_copy_of_a_listing_does_not_publish_it_twice(): void
    {
        $listing = Listing::factory()->draft()->create();
        $staleCopy = Listing::findOrFail($listing->id);

        $this->assertTrue(app(PublishListing::class)->handle($listing));
        $this->assertFalse(app(PublishListing::class)->handle($staleCopy));

        Event::assertDispatchedTimes(ListingWentLive::class, 1);
    }

    public function test_a_draft_is_listed_when_it_goes_live(): void
    {
        $this->freezeSecond();
        $listing = Listing::factory()->draft()->create();

        app(PublishListing::class)->handle($listing);

        $this->assertEquals(now(), $listing->fresh()->listed_at);
    }

    public function test_a_relisted_listing_keeps_its_original_listed_date(): void
    {
        $listing = Listing::factory()->underOffer()->create();
        $listedAt = $listing->fresh()->listed_at;

        app(PublishListing::class)->handle($listing);

        $this->assertEquals($listedAt, $listing->fresh()->listed_at);
    }

    public function test_no_event_fires_if_the_surrounding_transaction_rolls_back(): void
    {
        $listing = Listing::factory()->draft()->create();

        try {
            DB::transaction(function () use ($listing) {
                app(PublishListing::class)->handle($listing);

                throw new RuntimeException('Something later in the transaction failed.');
            });
        } catch (RuntimeException) {
            //
        }

        $this->assertSame(ListingStatus::Draft, $listing->fresh()->status);
        Event::assertNotDispatched(ListingWentLive::class);
    }
}
