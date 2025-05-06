<?php

use App\Models\Event;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    Event::findOrFail(1);
    return view('welcome');
});
