<?php

namespace App\Console\Commands;

use App\Enums\ListingStatus;
use App\Models\Listing;
use Illuminate\Console\Command;

/**
 * Shows what can be published, with the details a saved search matches on,
 * so alerts can be tried locally: save a search that fits one of these, then
 * run listing:publish with its reference.
 */
class ListUnpublishedListingsCommand extends Command
{
    protected $signature = 'listing:unpublished';

    protected $description = 'List listings that are not live, ready for listing:publish';

    public function handle(): int
    {
        $listings = Listing::query()
            ->where('status', '!=', ListingStatus::Live)
            ->with('branch')
            ->orderBy('reference')
            ->get();

        if ($listings->isEmpty()) {
            $this->info('Every listing is live.');

            return self::SUCCESS;
        }

        $this->table(
            ['Reference', 'Status', 'Type', 'Region', 'Beds', 'Price'],
            $listings->map(fn (Listing $listing) => [
                $listing->reference,
                $listing->status->label(),
                $listing->property_type->label(),
                $listing->branch->region,
                $listing->bedrooms,
                '£'.number_format($listing->price),
            ]),
        );

        $this->line('Publish one with: php artisan listing:publish {reference}');

        return self::SUCCESS;
    }
}
