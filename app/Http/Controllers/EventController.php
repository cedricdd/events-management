<?php

namespace App\Http\Controllers;

use App\Constants;
use App\Models\User;
use App\Models\Event;
use App\LoadRelationships;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Requests\EventRequest;
use App\Http\Resources\EventResource;
use App\Http\Resources\EventCollection;
use App\Notifications\EventCreationNotification;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Notifications\EventModificationNotification;

class EventController extends Controller
{
    use LoadRelationships;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, ?User $organizer = null): EventCollection|JsonResponse
    {
        [$order, $direction] = cleanSorting($request->input('sort', ''), 'event');

        $events = Event::status($request->input('past', false))
            ->withCount('attendees')
            ->when($organizer, fn($query) => $query->where('user_id', $organizer->id))
            ->orderBy(Constants::EVENT_SORTING_OPTIONS[$order], $direction)
            ->paginate(Constants::EVENTS_PER_PAGE);

        if ($request->has('page') && $request->input('page') > $events->lastPage()) {
            return response()->json([
                'message' => "The page " . $request->input('page') . " does not exist",
            ], 404);
        }

        // Only add the sort parameter to the URL if it is not the default sorting
        if ($order !== Constants::EVENT_DEFAULT_SORTING || $direction !== 'asc') {
            $events->appends(['sort' => $order . ',' . $direction]);
        }

        return new EventCollection($this->loadRelationships($events, ['organizer']));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(EventRequest $request): JsonResponse
    {
        $event = new Event();
        $event->name = $request->name;
        $event->description = $request->description;
        $event->start_date = $request->start_date;
        $event->end_date = $request->end_date;
        $event->cost = $request->cost;
        $event->location = $request->location;
        $event->is_public = $request->is_public ? 1 : 0;
        $event->organizer()->associate($request->user());

        //Check if an event with the same values already exists
        if ($existingEvent = Event::where($event->getAttributes())->first()) {
            return response()->json([
                'message' => "A similar event already exists!",
                'event' => new EventResource($existingEvent),
            ], 409);
        }

        $event->save();

        $request->user()->notify(new EventCreationNotification($event));

        $event->setRelation('organizer', $request->user());

        return EventResource::make($event)
            ->additional(["message" => "Event created successfully"])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Event $event): EventResource
    {
        $event = $this->loadRelationships($event, ['organizer']);
        $event->loadCount('attendees');

        return EventResource::make($event);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(EventRequest $request, Event $event): JsonResponse
    {
        // If the event was supposed to start soon we don't allow any changes
        if (now()->addHours(Constants::MIN_HOURS_BEFORE_START_EVENT) > $event->start_date) {
            return response()->json([
                'message' => "The start of this event is too close, modification are not allowed anymore!",
            ], 403);
        }

        $event->name = $request->input('name', $event->name);
        $event->description = $request->input('description', $event->description);

        $event->loadCount('attendees');

        // Some user have already paid for the event, we don't allow any changes other than name & description
        if ($event->attendees_count == 0) {
            $event->start_date = $request->input('start_date', $event->start_date);
            $event->end_date = $request->input('end_date', $event->end_date);
            $event->cost = $request->input('cost', $event->cost);
            $event->location = $request->input('location', $event->location);
            $event->is_public = $request->input('is_public', $event->is_public) ? 1 : 0;
        }

        if($event->end_date <= $event->start_date) {
            return response()->json([
                'message' => "The end date must be after the start date.",
            ], 403);
        }

        $event->save();

        if (!$event->getChanges()) {
            return response()->json([
                'message' => "No changes were made to the event.",
            ], 409);
        }

        // Let the attendees know that the event has been modified
        foreach ($event->attendees as $attendee) {
            $attendee->notify(new EventModificationNotification($event, $event->getChanges()));
        }

        $event->load('organizer');

        return EventResource::make($event)
            ->additional(["message" => "Event updated successfully"])
            ->response();
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Event $event): Response
    {
        $event->delete();

        return response()->noContent();
    }
}