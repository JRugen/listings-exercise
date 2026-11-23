<?php

namespace App\Actions;

use App\Enums\ListingStatus;
use App\Events\ListingWentLive;
use App\Models\Listing;
use Illuminate\Support\Facades\DB;

/**
 * The only way a listing should go live, so ListingWentLive always fires
 * when it does.
 */
class PublishListing
{
    /**
     * Put a listing live, from any other status. Publishing a listing that's
     * already live changes nothing and fires no event, so this is safe to
     * repeat.
     *
     * @return bool Whether the listing went live.
     */
    public function handle(Listing $listing): bool
    {
        return DB::transaction(function () use ($listing) {
            // The status check is part of the update itself, so if two
            // publishes race, only one of them changes the row.
            $wentLive = Listing::query()
                ->whereKey($listing->getKey())
                ->where('status', '!=', ListingStatus::Live)
                ->update(['status' => ListingStatus::Live]) === 1;

            if (! $wentLive) {
                return false;
            }

            // A listing coming back from under offer or sold keeps the date
            // it was first listed; a draft is listed now.
            Listing::query()
                ->whereKey($listing->getKey())
                ->whereNull('listed_at')
                ->update(['listed_at' => now()]);

            $listing->refresh();

            ListingWentLive::dispatch($listing);

            return true;
        });
    }
}
