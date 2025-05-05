<?php

use App\Http\Controllers\AttendeeController;
use App\Http\Controllers\EventController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('events', [EventController::class, 'index'])->name('events.index');
Route::get('events/{event}/attendees', [AttendeeController::class, 'index'])->name('attendees.index');
