<?php

namespace App\Http\Controllers;

use App\Constants;
use App\Http\Resources\EventResource;
use App\Models\Event;
use Illuminate\Http\Request;
use App\Http\Resources\UserCollection;

class AttendeeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Event $event)
    {
        $event->load('user');

        // Return the list of attendees
        return new UserCollection($event->attendees()
            ->orderBy('name')
            ->paginate(Constants::ATTENDEES_PER_PAGE))
            ->additional(['event' => EventResource::make($event)]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
