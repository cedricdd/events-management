<?php

namespace App\Policies;

use App\Models\EventType;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class EventTypePolicy
{
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, EventType $eventType): bool
    {
        return $user->isAdmin();
    }

    public function destroy(User $user, EventType $eventType): bool
    {
        return $user->isAdmin();
    }
}
