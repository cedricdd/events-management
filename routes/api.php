<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\AttendeeController;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::post('events', [EventController::class, 'store'])->name('events.store');
    Route::put('events/{event}', [EventController::class, 'update'])->name('events.update');
    Route::delete('events/{event}', [EventController::class, 'destroy'])->name('events.destroy');

    Route::post('events/{event}/attendees', [AttendeeController::class, 'store'])->name('attendees.store');
    Route::delete('events/{event}/attendees/{attendee}', [AttendeeController::class, 'destroy'])->name('attendees.destroy');

    Route::delete('logout', [AuthController::class, 'logout'])->name('logout');
});

Route::middleware('guest:sanctum')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->name('login');
});

Route::get('events', [EventController::class, 'index'])->name('events.index');
Route::get('events/{event}', [EventController::class, 'show'])->name('events.show');

Route::get('events/{event}/attendees', [AttendeeController::class, 'index'])->name('attendees.index');
Route::get('events/{event}/attendees/{attendee}', [AttendeeController::class, 'show'])->name('attendees.show');