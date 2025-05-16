<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class EventPolicy
{
    public function store(User $user): bool
    {
        return $user->role == "admin" || $user->role == "organizer";
    }

    public function update(User $user, Event $event): bool
    {
        return $user->role == "admin" || $event->organizer()->is($user);
    }

    public function destroy(User $user, Event $event): bool
    {
        return $user->role == "admin" || $event->organizer()->is($user);
    }
}
