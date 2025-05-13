<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class EventPolicy
{
    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Event $event): bool
    {
        return $event->organizer()->is($user);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function destroy(User $user, Event $event): bool
    {
        return $event->organizer()->is($user);
    }
}
