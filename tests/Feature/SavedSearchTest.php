<?php

namespace Tests\Feature;

use App\Enums\PropertyType;
use App\Models\Branch;
use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SavedSearchTest extends TestCase
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

    public function test_index_lists_only_the_users_own_searches(): void
    {
        SavedSearch::factory(2)->for($this->user)->create();
        SavedSearch::factory()->create(); // someone else's

        $this->get('/saved-searches')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('SavedSearches/Index')
                ->has('savedSearches', 2)
            );
    }

    public function test_a_search_with_a_single_criterion_is_saved_for_the_user(): void
    {
        $this->post('/saved-searches', ['property_type' => 'flat'])
            ->assertRedirect('/saved-searches');

        $search = $this->user->savedSearches()->sole();
        $this->assertSame(PropertyType::Flat, $search->property_type);
        $this->assertNull($search->region);
        $this->assertNull($search->min_bedrooms);
        $this->assertNull($search->max_price);
    }

    public function test_a_search_with_every_criterion_is_saved_for_the_user(): void
    {
        Branch::factory()->create(['region' => 'Leeds']);

        $this->post('/saved-searches', [
            'property_type' => 'terraced',
            'region' => 'Leeds',
            'min_bedrooms' => '3',
            'max_price' => '250000',
        ])->assertRedirect('/saved-searches');

        $search = $this->user->savedSearches()->sole();
        $this->assertSame(PropertyType::Terraced, $search->property_type);
        $this->assertSame('Leeds', $search->region);
        $this->assertSame(3, $search->min_bedrooms);
        $this->assertSame(250_000, $search->max_price);
    }

    /**
     * Blank fields arrive from the filter form as empty strings, so they have
     * to count as "no criterion" too.
     *
     * @param  array<string, string>  $input
     */
    #[DataProvider('searchesWithNoCriteria')]
    public function test_a_search_needs_at_least_one_criterion(array $input): void
    {
        $this->post('/saved-searches', $input)
            ->assertSessionHasErrors('criteria');

        $this->assertDatabaseCount('saved_searches', 0);
    }

    /**
     * @return array<string, array{array<string, string>}>
     */
    public static function searchesWithNoCriteria(): array
    {
        return [
            'nothing sent' => [[]],
            'all blank' => [['property_type' => '', 'region' => '', 'min_bedrooms' => '', 'max_price' => '']],
        ];
    }

    public function test_a_region_must_belong_to_a_branch(): void
    {
        Branch::factory()->create(['region' => 'Leeds']);

        $this->post('/saved-searches', ['region' => 'Atlantis'])
            ->assertSessionHasErrors('region');

        $this->assertDatabaseCount('saved_searches', 0);
    }

    public function test_zero_minimum_bedrooms_is_not_a_criterion(): void
    {
        $this->post('/saved-searches', ['min_bedrooms' => '0'])
            ->assertSessionHasErrors('min_bedrooms');
    }

    public function test_saving_the_same_search_twice_keeps_one(): void
    {
        $this->post('/saved-searches', ['property_type' => 'flat', 'max_price' => '300000']);

        // Blank fields and number strings describe the same search.
        $this->post('/saved-searches', ['property_type' => 'flat', 'max_price' => '300000', 'region' => ''])
            ->assertRedirect('/saved-searches');

        $this->assertSame(1, $this->user->savedSearches()->count());
    }

    /**
     * Unset criteria still have to match: saving "flats" when "flats in Leeds"
     * is saved is a different, broader search.
     */
    public function test_a_broader_search_is_not_a_duplicate(): void
    {
        Branch::factory()->create(['region' => 'Leeds']);
        SavedSearch::factory()->for($this->user)->create([
            'property_type' => PropertyType::Flat,
            'region' => 'Leeds',
            'min_bedrooms' => null,
            'max_price' => null,
        ]);

        $this->post('/saved-searches', ['property_type' => 'flat']);

        $this->assertSame(2, $this->user->savedSearches()->count());
    }

    public function test_another_users_identical_search_is_not_a_duplicate(): void
    {
        SavedSearch::factory()->create([
            'property_type' => PropertyType::Flat,
            'region' => null,
            'min_bedrooms' => null,
            'max_price' => null,
        ]);

        $this->post('/saved-searches', ['property_type' => 'flat']);

        $this->assertSame(1, $this->user->savedSearches()->count());
    }

    public function test_a_user_can_delete_their_own_search(): void
    {
        $search = SavedSearch::factory()->for($this->user)->create();

        $this->delete("/saved-searches/{$search->id}")
            ->assertRedirect('/saved-searches');

        $this->assertModelMissing($search);
    }

    public function test_a_user_cannot_delete_someone_elses_search(): void
    {
        $search = SavedSearch::factory()->create();

        $this->delete("/saved-searches/{$search->id}")->assertNotFound();

        $this->assertModelExists($search);
    }
}
