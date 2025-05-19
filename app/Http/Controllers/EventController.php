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
use Symfony\Component\HttpFoundation\JsonResponse;

class EventController extends Controller
{
    use LoadRelationships;

    private $defaultRelationships = [
        'organizer',
        'attendees',
    ];

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, ?User $organizer): EventCollection|JsonResponse
    {
        [$order, $direction] = cleanSorting($request->input('sort', ''), 'event');

        $events = Event::isActive()
            ->withCount('attendees')
            ->when($organizer, fn ($query) => $query->where('user_id', $organizer->id))
            ->orderBy(Constants::EVENT_SORTING_OPTIONS[$order], $direction)
            ->paginate(Constants::EVENTS_PER_PAGE);

        if($request->has('page') && $request->input('page') > $events->lastPage()) {
            return response()->json([
                'message' => "The page " . $request->input('page') . " does not exist",
            ], 404);
        }

        $events->appends(['sort' => $order . ',' . $direction]);

        return new EventCollection($this->loadRelationships($events, $this->defaultRelationships));
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
        $event->is_public = $request->is_public;
        $event->organizer()->associate($request->user())->save();

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
        $event = $this->loadRelationships($event, $this->defaultRelationships);
        $event->loadCount('attendees');

        return EventResource::make($event);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Event $event): JsonResponse
    {
        $event->name = $request->input('name', $event->name);
        $event->description = $request->input('description', $event->description);
        $event->start_date = $request->input('start_date', $event->start_date);
        $event->end_date = $request->input('end_date', $event->end_date);
        $event->cost = $request->input('cost', $event->cost);
        $event->location = $request->input('location', $event->location);
        $event->is_public = $request->input('is_public', $event->is_public);
        $event->save();

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