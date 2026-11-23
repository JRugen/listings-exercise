<?php

namespace App\Listeners;

use App\Enums\ListingStatus;
use App\Events\ListingWentLive;
use App\Models\ListingAlert;
use App\Models\SavedSearch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;

/**
 * Alerts every user with a saved search the new listing matches.
 *
 * Queued, because a listing can match a lot of searches and publishing
 * shouldn't wait for that. If the listing is deleted before the job runs,
 * the job is dropped rather than failed.
 */
#[DeleteWhenMissingModels]
class CreateListingAlerts implements ShouldQueue
{
    public function handle(ListingWentLive $event): void
    {
        $listing = $event->listing;

        // It may have come off the market again since the job was queued.
        if ($listing->status !== ListingStatus::Live) {
            return;
        }

        $now = now();

        // One INSERT ... SELECT, so matching searches never load into PHP.
        // DISTINCT and the unique (listing_id, user_id) key mean a user gets
        // one alert per listing, however many of their searches match and
        // however often this runs.
        ListingAlert::query()->insertOrIgnoreUsing(
            ['user_id', 'listing_id', 'created_at', 'updated_at'],
            SavedSearch::query()
                ->matching($listing)
                ->distinct()
                ->select('user_id')
                ->selectRaw('? as listing_id', [$listing->id])
                ->selectRaw('? as created_at', [$now])
                ->selectRaw('? as updated_at', [$now]),
        );
    }
}
