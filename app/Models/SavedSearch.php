<?php

namespace App\Models;

use App\Enums\PropertyType;
use Database\Factories\SavedSearchFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Each criterion is nullable; null means the search doesn't filter on it.
 *
 * @property int $id
 * @property int $user_id
 * @property PropertyType|null $property_type
 * @property string|null $region
 * @property int|null $min_bedrooms
 * @property int|null $max_price
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $user
 */
class SavedSearch extends Model
{
    /** @use HasFactory<SavedSearchFactory> */
    use HasFactory;

    /**
     * The criteria a saved search can hold. A blank criterion means "any".
     */
    public const CRITERIA = ['property_type', 'region', 'min_bedrooms', 'max_price'];

    protected $fillable = [
        'user_id',
        'property_type',
        'region',
        'min_bedrooms',
        'max_price',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'property_type' => PropertyType::class,
            'min_bedrooms' => 'integer',
            'max_price' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Searches the listing satisfies: every criterion that's set has to
     * match, and blank criteria are ignored. Both limits are inclusive, so a
     * £300,000 listing matches "up to £300,000".
     *
     * The listing's region lives on its branch, so it's looked up in a
     * subquery rather than by loading the branch first.
     *
     * This is the listings page filter (ListingController::index) seen from
     * the other side, so change both together. MatchingConsistencyTest
     * checks they agree.
     *
     * @param  Builder<SavedSearch>  $query
     */
    public function scopeMatching(Builder $query, Listing $listing): void
    {
        $branchRegion = Branch::query()->select('region')->whereKey($listing->branch_id);

        $query
            ->where(fn (Builder $q) => $q->whereNull('property_type')->orWhere('property_type', $listing->property_type))
            ->where(fn (Builder $q) => $q->whereNull('region')->orWhere('region', $branchRegion))
            ->where(fn (Builder $q) => $q->whereNull('min_bedrooms')->orWhere('min_bedrooms', '<=', $listing->bedrooms))
            ->where(fn (Builder $q) => $q->whereNull('max_price')->orWhere('max_price', '>=', $listing->price));
    }
}
