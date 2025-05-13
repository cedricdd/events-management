<?php

namespace App\Providers;

use App\Models\User;
use App\Models\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('destroy-attendee', function (User $user, Event $event, int $attendeeID) {
             return $user->id === $attendeeID || $user->id === $event->user_id;
        });

    }
}
