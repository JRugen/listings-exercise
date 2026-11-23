<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Listing;
use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * "Does this listing match this search?" is answered in two places, from
 * opposite directions: the listings page filters listings by a search's
 * criteria, and SavedSearch::scopeMatching finds the searches a new listing
 * matches. They can't share a query, so this keeps them agreeing: a saved
 * search's "View listings" link should show exactly the listings it alerts on.
 */
class MatchingConsistencyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, string|int>  $criteria
     */
    #[DataProvider('cases')]
    public function test_the_listings_page_and_alerts_agree_on_a_match(array $criteria, bool $matches): void
    {
        User::factory()->create(); // the user the auth stub signs in

        // A 3-bed flat in Leeds at £300,000.
        $listing = Listing::factory()->live()
            ->for(Branch::factory()->create(['region' => 'Leeds']))
            ->create(['property_type' => 'flat', 'bedrooms' => 3, 'price' => 300_000]);

        $search = SavedSearch::factory()->create([
            'property_type' => null,
            'region' => null,
            'min_bedrooms' => null,
            'max_price' => null,
            ...$criteria,
        ]);

        $shownOnListingsPage = collect(
            $this->get('/?'.http_build_query([...$criteria, 'per_page' => 100]))
                ->viewData('page')['props']['listings']['data']
        )->contains('id', $listing->id);

        $alertedOn = SavedSearch::query()->matching($listing)->whereKey($search->id)->exists();

        $this->assertSame($matches, $shownOnListingsPage, 'Listings page');
        $this->assertSame($matches, $alertedOn, 'Alert matching');
    }

    /**
     * @return array<string, array{array<string, string|int>, bool}>
     */
    public static function cases(): array
    {
        return [
            'same type' => [['property_type' => 'flat'], true],
            'different type' => [['property_type' => 'detached'], false],
            'same region' => [['region' => 'Leeds'], true],
            'different region' => [['region' => 'Manchester'], false],
            'min beds below the listing' => [['min_bedrooms' => 2], true],
            'min beds equal to the listing' => [['min_bedrooms' => 3], true],
            'min beds above the listing' => [['min_bedrooms' => 4], false],
            'max price above the listing' => [['max_price' => 300_001], true],
            'max price equal to the listing' => [['max_price' => 300_000], true],
            'max price below the listing' => [['max_price' => 299_999], false],
            'every criterion matching' => [['property_type' => 'flat', 'region' => 'Leeds', 'min_bedrooms' => 3, 'max_price' => 300_000], true],
            'one of several not matching' => [['property_type' => 'flat', 'region' => 'Leeds', 'min_bedrooms' => 3, 'max_price' => 299_999], false],
        ];
    }
}
