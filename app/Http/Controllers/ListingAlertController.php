<?php

namespace App\Http\Controllers;

use App\Http\Resources\ListingAlertResource;
use App\Models\Listing;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ListingAlertController extends Controller
{
    /**
     * The user's alerts, newest first. Alerts for listings that have since
     * come off the market are left out: there's nothing to act on, and the
     * listing page wouldn't show them anyway.
     */
    public function index(Request $request): Response
    {
        $alerts = $request->user()->listingAlerts()
            ->whereIn('listing_id', Listing::query()->live()->select('id'))
            ->with('listing.branch')
            ->latest()
            ->orderByDesc('id')
            ->paginate(15);

        return Inertia::render('Alerts/Index', [
            'alerts' => ListingAlertResource::collection($alerts),
        ]);
    }
}
