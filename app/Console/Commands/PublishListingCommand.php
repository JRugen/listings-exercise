<?php

namespace App\Console\Commands;

use App\Actions\PublishListing;
use App\Models\Listing;
use Illuminate\Console\Command;

class PublishListingCommand extends Command
{
    protected $signature = 'listing:publish {reference : The listing reference, e.g. STR-123456}';

    protected $description = 'Put a listing live';

    public function handle(PublishListing $publishListing): int
    {
        $reference = (string) $this->argument('reference');

        $listing = Listing::query()->where('reference', $reference)->first();

        if ($listing === null) {
            $this->error("No listing found with reference {$reference}.");

            return self::FAILURE;
        }

        if (! $publishListing->handle($listing)) {
            $this->info("{$reference} is already live.");

            return self::SUCCESS;
        }

        $this->info("{$reference} is now live.");

        return self::SUCCESS;
    }
}
