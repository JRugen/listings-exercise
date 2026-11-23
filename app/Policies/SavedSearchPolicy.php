<?php

namespace App\Policies;

use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SavedSearchPolicy
{
    /**
     * Someone else's saved search is a 404 rather than a 403, so ids can't be
     * probed for existence.
     */
    public function delete(User $user, SavedSearch $savedSearch): Response
    {
        return $savedSearch->user_id === $user->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
