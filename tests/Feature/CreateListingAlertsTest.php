<?php

namespace Tests\Feature;

use App\Actions\PublishListing;
use App\Enums\ListingStatus;
use App\Enums\PropertyType;
use App\Events\ListingWentLive;
use App\Listeners\CreateListingAlerts;
use App\Models\Branch;
use App\Models\Listing;
use App\Models\ListingAlert;
use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * These go through PublishListing with the real event and the sync queue,
 * so they cover what actually happens when a listing goes live.
 */
class CreateListingAlertsTest extends TestCase
{
    use RefreshDatabase;

    private Listing $listing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->listing = Listing::factory()->draft()
            ->for(Branch::factory()->create(['region' => 'Leeds']))
            ->create([
                'property_type' => PropertyType::Flat,
                'bedrooms' => 3,
                'price' => 300_000,
            ]);
    }

    /**
     * The listing is a 3-bed flat in Leeds at £300,000. Every criterion that's
     * set has to match; blank ones are ignored.
     *
     * @param  array<string, mixed>  $criteria
     */
    #[DataProvider('searches')]
    public function test_a_user_is_alerted_only_when_every_set_criterion_matches(array $criteria, bool $alerted): void
    {
        $search = $this->savedSearch($criteria);

        $this->publish();

        $this->assertSame($alerted, ListingAlert::query()->where('user_id', $search->user_id)->exists());
    }

    /**
     * @return array<string, array{array<string, mixed>, bool}>
     */
    public static function searches(): array
    {
        return [
            'same type' => [['property_type' => PropertyType::Flat], true],
            'different type' => [['property_type' => PropertyType::Detached], false],
            'same region' => [['region' => 'Leeds'], true],
            'different region' => [['region' => 'Manchester'], false],
            'min beds equal to the listing' => [['min_bedrooms' => 3], true],
            'min beds above the listing' => [['min_bedrooms' => 4], false],
            'max price equal to the listing' => [['max_price' => 300_000], true],
            'max price below the listing' => [['max_price' => 299_999], false],
            'every criterion matching' => [['property_type' => PropertyType::Flat, 'region' => 'Leeds', 'min_bedrooms' => 2, 'max_price' => 350_000], true],
            'one of several not matching' => [['property_type' => PropertyType::Flat, 'region' => 'Leeds', 'min_bedrooms' => 2, 'max_price' => 250_000], false],
        ];
    }

    public function test_a_user_with_several_matching_searches_gets_one_alert(): void
    {
        $user = User::factory()->create();
        $this->savedSearch(['property_type' => PropertyType::Flat], $user);
        $this->savedSearch(['region' => 'Leeds'], $user);
        $this->savedSearch(['max_price' => 400_000], $user);

        $this->publish();

        $this->assertSame(1, ListingAlert::query()->where('user_id', $user->id)->count());
    }

    public function test_each_matching_user_gets_their_own_alert(): void
    {
        $first = $this->savedSearch(['property_type' => PropertyType::Flat]);
        $second = $this->savedSearch(['region' => 'Leeds']);
        $this->savedSearch(['region' => 'Manchester']);

        $this->publish();

        $this->assertEqualsCanonicalizing(
            [$first->user_id, $second->user_id],
            ListingAlert::query()->where('listing_id', $this->listing->id)->pluck('user_id')->all(),
        );
    }

    /**
     * The insert is raw SQL, so Eloquent doesn't fill the timestamps in.
     */
    public function test_alerts_have_timestamps(): void
    {
        $this->freezeSecond();
        $this->savedSearch(['property_type' => PropertyType::Flat]);

        $this->publish();

        $alert = ListingAlert::query()->sole();
        $this->assertEquals(now(), $alert->created_at);
        $this->assertEquals(now(), $alert->updated_at);
    }

    public function test_a_relisted_listing_does_not_alert_the_same_user_twice(): void
    {
        $this->savedSearch(['property_type' => PropertyType::Flat]);
        $this->publish();

        $this->listing->update(['status' => ListingStatus::UnderOffer]);
        $this->publish();

        $this->assertSame(1, ListingAlert::query()->count());
    }

    public function test_searches_saved_after_a_listing_went_live_are_not_backfilled(): void
    {
        $this->publish();

        $this->savedSearch(['property_type' => PropertyType::Flat]);

        $this->assertSame(0, ListingAlert::query()->count());
    }

    public function test_nothing_happens_if_the_listing_is_no_longer_live_when_the_job_runs(): void
    {
        $this->savedSearch(['property_type' => PropertyType::Flat]);

        // The listing never went live, as if it came off the market again
        // between the event firing and the queued job running.
        app(CreateListingAlerts::class)->handle(new ListingWentLive($this->listing));

        $this->assertSame(0, ListingAlert::query()->count());
    }

    public function test_the_job_is_dropped_without_failing_if_the_listing_was_deleted(): void
    {
        $this->savedSearch(['property_type' => PropertyType::Flat]);
        $event = new ListingWentLive($this->listing);
        $this->listing->delete();

        // The sync queue still serialises the job, so the missing listing is
        // hit when it's restored, just as it would be on a real worker.
        event($event);

        $this->assertSame(0, ListingAlert::query()->count());
    }

    private function publish(): void
    {
        app(PublishListing::class)->handle($this->listing);
    }

    /**
     * A saved search with only the given criteria set.
     *
     * @param  array<string, mixed>  $criteria
     */
    private function savedSearch(array $criteria, ?User $user = null): SavedSearch
    {
        return SavedSearch::factory()
            ->for($user ?? User::factory())
            ->create([
                'property_type' => null,
                'region' => null,
                'min_bedrooms' => null,
                'max_price' => null,
                ...$criteria,
            ]);
    }
}
