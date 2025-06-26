<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Event;
use App\Models\Invite;
use Illuminate\Auth\Access\Response;

class InvitePolicy
{
    public function index(User $user, Event $event): bool
    {
        // Only organizers & admins can view invites for private events
        return $user->isAdmin() || $event->user_id == $user->id;
    }

    public function store(User $user, Event $event): bool
    {
        // Only organizers can create invites for private events
        return $event->user_id == $user->id;
    }

    public function destroy(User $user, Event $event): bool
    {
        // Only organizers can delete invites for private events
        return $event->user_id == $user->id;
    }
}
