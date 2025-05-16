<?php


use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\AttendeeController;
use App\Http\Controllers\UserController;
use App\Models\Event;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('user', [UserController::class, 'show'])->name('user.show');

    Route::post('events', [EventController::class, 'store'])->name('events.store')->can('store', Event::class);
    Route::put('events/{event}', [EventController::class, 'update'])->name('events.update')->can('update', 'event');
    Route::delete('events/{event}', [EventController::class, 'destroy'])->name('events.destroy')->can('destroy', 'event');

    Route::post('events/{event}/attendees', [AttendeeController::class, 'store'])->name('attendees.store');
    Route::delete('events/{event}/attendees/{attendee}', [AttendeeController::class, 'destroy'])->name('attendees.destroy')->can('destroy-attendee', ['event', 'attendee']);

    Route::delete('logout', [AuthController::class, 'logout'])->name('logout');
});

Route::middleware('guest:sanctum')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->name('login');
});

Route::get('events', [EventController::class, 'index'])->name('events.index');
Route::get('events/{event}', [EventController::class, 'show'])->name('events.show');

Route::get('events/{event}/attendees', [AttendeeController::class, 'index'])->name('attendees.index');
Route::get('events/{event}/attendees/{attendee}', [AttendeeController::class, 'show'])->name('attendees.show');

//Search events
//User list for admins
//Past events
//Categories
//private events
//events by organizer