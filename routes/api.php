<?php

use App\Http\Controllers\AttendeeController;
use App\Http\Controllers\EventController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('events', [EventController::class, 'index'])->name('events.index');
Route::post('events', [EventController::class, 'store'])->name('events.store');
Route::get('events/{event}', [EventController::class, 'show'])->name('events.show');


Route::get('events/{event}/attendees', [AttendeeController::class, 'index'])->name('attendees.index');
