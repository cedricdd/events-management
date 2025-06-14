<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Event;
use App\Models\Invite;

class InvintePolicy
{
    /**
     * Determine whether the user can create models.
     */
    public function store(User $user): bool
    {
        return $user->role == 'organizer';
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function destroy(User $user, Invite $invite): bool
    {
        return $invite->event->organizer_id == $user->id;
    }
}
