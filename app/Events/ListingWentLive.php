<?php

namespace App\Events;

use App\Models\Listing;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A listing has just gone live. Only fired by PublishListing, so listings
 * created as live (seeders, factories) don't fire it.
 *
 * Held until the surrounding transaction commits, so listeners never see a
 * listing whose change was rolled back.
 */
class ListingWentLive implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public Listing $listing) {}
}
