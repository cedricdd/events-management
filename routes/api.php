<?php


use App\Models\Event;
use App\Models\EventType;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\AttendeeController;
use App\Http\Controllers\EventTypeController;

Route::middleware(['auth:sanctum', 'throttle:10,1'])->group(function () {
    Route::get('user/{user?}', [UserController::class, 'show'])->name('user.show')->where('user', '[0-9]+');

    Route::post('events', [EventController::class, 'store'])->name('events.store')->can('store', Event::class);
    Route::put('events/{event}', [EventController::class, 'update'])->name('events.update')->can('update', 'event')->where('event', '[0-9]+');
    Route::delete('events/{event}', [EventController::class, 'destroy'])->name('events.destroy')->can('destroy', 'event')->where('event', '[0-9]+');

    Route::post('events/{event}/attendees', [AttendeeController::class, 'store'])->name('attendees.store')->where('event', '[0-9]+');
    Route::delete('events/{event}/attendees/{attendee}', [AttendeeController::class, 'destroy'])->name('attendees.destroy')->can('destroy-attendee', ['event', 'attendee'])->where(['event' => '[0-9]+', 'attendee' => '[0-9]+']);

    Route::post('event-types', [EventTypeController::class, 'store'])->name('event-types.store')->can('store', EventType::class);

    Route::delete('logout', [AuthController::class, 'logout'])->name('logout');
});

Route::middleware('guest:sanctum')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->name('login');
});

Route::get('events', [EventController::class, 'index'])->name('events.index');
Route::get('events/type/{name}', [EventController::class, 'type'])->name('events.type')->where('name', '[a-zA-Z ]+');
Route::get('events/organizer/{organizer}', [EventController::class, 'index'])->name('events.organizer')->where('id', '[0-9]+');
Route::get('events/{event}', [EventController::class, 'show'])->name('events.show')->where('event', '[0-9]+');

Route::get('events/{event}/attendees', [AttendeeController::class, 'index'])->name('attendees.index')->where('event', '[0-9]+');
Route::get('events/{event}/attendees/{attendee}', [AttendeeController::class, 'show'])->name('attendees.show')->where(['event' => '[0-9]+', 'attendee' => '[0-9]+']);

Route::get('event-types', [EventTypeController::class, 'index'])->name('event-types.index');

//Search events
//Categories
//private events