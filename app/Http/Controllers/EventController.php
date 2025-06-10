<?php

namespace App\Http\Controllers;

use App\Constants;
use App\Models\User;
use App\Models\Event;
use App\Models\EventType;
use App\LoadRelationships;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Requests\EventRequest;
use App\Http\Requests\SearchEventRequest;
use App\Http\Resources\EventResource;
use App\Http\Resources\EventCollection;
use Illuminate\Support\Facades\Notification;
use App\Jobs\SendEventModificationNotification;
use App\Notifications\EventCreationNotification;
use App\Notifications\EventDeletionNotification;
use Symfony\Component\HttpFoundation\JsonResponse;

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
            ->with('type')
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
        $type = EventType::where('name', $request->type)->first();

        $event = new Event();
        $event->name = $request->name;
        $event->description = $request->description;
        $event->start_date = $request->start_date;
        $event->end_date = $request->end_date;
        $event->cost = $request->cost;
        $event->location = $request->location;
        $event->is_public = $request->is_public ? 1 : 0;
        $event->type()->associate($type);
        $event->organizer()->associate($request->user());

        //Check if an event with the same values already exists
        if ($existingEvent = Event::with('type')->where($event->getAttributes())->first()) {
            return response()->json([
                'message' => "A similar event already exists!",
                'event' => new EventResource($existingEvent),
            ], 409);
        }

        $event->save();

        $request->user()->notify(new EventCreationNotification($event));

        $event->setRelation('organizer', $request->user());
        $event->setRelation('type', $type);

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
        $event->load('type');

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
        $event->type()->associate(EventType::where('name', $request->input('type', $event->type->name))->first());

        $event->loadCount('attendees');

        // Some user have already paid for the event, we don't allow any changes other than name & description
        if ($event->attendees_count == 0) {
            $event->start_date = $request->input('start_date', $event->start_date);
            $event->end_date = $request->input('end_date', $event->end_date);
            $event->cost = $request->input('cost', $event->cost);
            $event->location = $request->input('location', $event->location);
            $event->is_public = $request->input('is_public', $event->is_public) ? 1 : 0;
        }

        if ($event->end_date <= $event->start_date) {
            return response()->json([
                'message' => "The end date must be after the start date.",
            ], 403);
        }

        // We don't allow for the creation of dupe events
        if ($existingEvent = Event::where(Arr::except($event->getAttributes(), ['id', 'attendees_count', 'created_at', 'updated_at']))->where('id', '!=', $event->id)->first()) {
            return response()->json([
                'message' => "A similar event already exists!",
                'event' => new EventResource($existingEvent),
            ], 409);
        }

        $event->save();

        if (!$event->getChanges()) {
            return response()->json([
                'message' => "No changes were made to the event.",
            ], 409);
        }

        // Let the attendees know that the event has been modified, organier might have messed up something, don't directly notify them
        SendEventModificationNotification::dispatch($event->id)->delay(now()->addMinutes(value: 30));

        $event->load('organizer');

        return EventResource::make($event)
            ->additional(["message" => "Event updated successfully"])
            ->response();
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Event $event): JsonResponse|Response
    {
        // If the event was supposed to start soon we don't allow deletion unless the user doing it is an admin
        if (!$request->user()->isAdmin() && now()->addHours(Constants::MIN_HOURS_BEFORE_START_EVENT) > $event->start_date) {
            return response()->json([
                'message' => "The deletion of this event is not allowed anymore!",
            ], 403);
        }

        // If the event has attendees, we need to refund them unless if it's an admin deleting an event that has already started or is over
        if ($event->start_date > now()) {
            foreach ($event->attendees as $attendee) {
                $attendee->increment('tokens', $event->cost);
                $attendee->decrement('tokens_spend', $event->cost);
            }

            // Let the attendees know that the event has been deleted
            Notification::send($event->attendees, new EventDeletionNotification($event->getAttributes(), $request->user()));
        }

        $event->delete();

        return response()->noContent();
    }

    public function type(Request $request, string $name): EventCollection|JsonResponse
    {
        $eventType = EventType::where('name', $name)->first();

        if (!$eventType) {
            return response()->json([
                'message' => "There are no events of this type.",
            ], 404);
        }

        [$order, $direction] = cleanSorting($request->input('sort', ''), 'event');

        $events = $eventType->events()
            ->status($request->input('past', false))
            ->with('type')
            ->withCount('attendees')
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

    public function search(SearchEventRequest $request): EventCollection|JsonResponse
    {
        [$order, $direction] = cleanSorting($request->input('sort', ''), 'event');

        $events = Event::with('type')
            ->withCount('attendees')
            ->when($request->has('name'), function ($query) use ($request) {
                $name = $request->input('name');
                $operator = "LIKE";

                if($name[0] === '-') {
                    $operator = "NOT LIKE";
                    $name = substr($name, 1);
                } 

                $query->where('name', $operator, '%' . $name . '%');
            })
            ->orderBy(Constants::EVENT_SORTING_OPTIONS[$order], $direction)
            ->paginate(Constants::EVENTS_PER_PAGE);

        if ($request->has('page') && $request->input('page') > $events->lastPage()) {
            return response()->json([
                'message' => "The page " . $request->input('page') . " does not exist",
            ], 404);
        }

        $events->appends($request->only(['name']));

        // Only add the sort parameter to the URL if it is not the default sorting
        if ($order !== Constants::EVENT_DEFAULT_SORTING || $direction !== 'asc') {
            $events->appends(['sort' => $order . ',' . $direction]);
        }

        return new EventCollection($this->loadRelationships($events, ['organizer']));
    }
}