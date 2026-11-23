<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSavedSearchRequest;
use App\Http\Resources\SavedSearchResource;
use App\Models\SavedSearch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SavedSearchController extends Controller
{
    public function index(Request $request): Response
    {
        $savedSearches = $request->user()->savedSearches()->latest()->orderByDesc('id')->get();

        return Inertia::render('SavedSearches/Index', [
            'savedSearches' => SavedSearchResource::collection($savedSearches),
        ]);
    }

    /**
     * Saving a search the user already has is a no-op rather than a duplicate,
     * so it lands on the same saved searches page either way.
     */
    public function store(StoreSavedSearchRequest $request): RedirectResponse
    {
        // Blank criteria aren't sent, so fill them in as null. Otherwise
        // firstOrCreate would ignore them and match a broader saved search.
        $request->user()->savedSearches()->firstOrCreate([
            ...array_fill_keys(SavedSearch::CRITERIA, null),
            ...$request->validated(),
        ]);

        return to_route('saved-searches.index');
    }

    /**
     * Ownership is checked by SavedSearchPolicy on the route.
     */
    public function destroy(SavedSearch $savedSearch): RedirectResponse
    {
        $savedSearch->delete();

        return to_route('saved-searches.index');
    }
}
