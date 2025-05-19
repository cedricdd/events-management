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
        Gate::define('destroy-attendee', function (User $user, Event $event, User $attendee) {
             return $user->id === $attendee->id || $user->id === $event->user_id;
        });

    }
}
