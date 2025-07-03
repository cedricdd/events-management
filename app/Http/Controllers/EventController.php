<?php

namespace App\Http\Controllers;

use App\Constants;
use App\Models\User;
use App\Models\Event;
use App\Models\EventType;
use App\LoadRelationships;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use App\Http\Requests\EventRequest;
use App\Http\Resources\EventResource;
use App\Http\Resources\EventCollection;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\UrlParam;
use App\Http\Requests\SearchEventRequest;
use Knuckles\Scribe\Attributes\BodyParam;
use Knuckles\Scribe\Attributes\QueryParam;
use Illuminate\Support\Facades\Notification;
use App\Jobs\SendEventModificationNotification;
use App\Notifications\EventCreationNotification;
use App\Notifications\EventDeletionNotification;
use Symfony\Component\HttpFoundation\JsonResponse;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;

/**
 * @group Events
 *
 * APIs for managing events.
 */
class EventController extends Controller
{
    use LoadRelationships;

    /**
     * List Events
     * 
     * Display a listing of events. By default, it shows only upcoming events.
     */
    #
    #[QueryParam("page", "int", "The results are paginated, you will get " . Constants::EVENTS_PER_PAGE . " results per page.", false, 2)]
    #[QueryParam("with", "string", "The additional data to include in the response.", false, enum: ["organizer"])]
    #[QueryParam("sort", "string", "The sorting criteria for the events. Default is 'start,*order*'.<br/>Consisting of two parts, the sorting criteria and the sorting order (asc or desc).", false, "cost,desc", enum: ["start,*order*", "name,*order*", "end,*order*", "cost,*order*", "attendees,*order*", "newest,*order*"])]
    
    #[Response('{"message": "The page 10 does not exist."}', 404)]
    #[ResponseFromApiResource(EventCollection::class, Event::class, 200, paginate: Constants::EVENTS_PER_PAGE, with: ['type'], withCount: ['attendees'])]
    public function index(Request $request): EventCollection|JsonResponse
    {
        [$order, $direction] = cleanSorting($request->input('sort', ''), 'event');

        $events = Event::status($request->input('past', false))
            ->with(['type', 'attendees'])
            ->withCount('attendees')
            ->orderBy(Constants::EVENT_SORTING_OPTIONS[$order], $direction)
            ->paginate(Constants::EVENTS_PER_PAGE);

        if ($request->has('page') && $request->input('page') > $events->lastPage()) {
            return response()->json([
                'message' => "The page " . $request->input('page') . " does not exist.",
            ], 404);
        }

        // Only add the sort parameter to the URL if it is not the default sorting
        if ($order !== Constants::EVENT_DEFAULT_SORTING || $direction !== 'asc') {
            $events->appends(['sort' => $order . ',' . $direction]);
        }

        return new EventCollection($this->loadRelationships($events, ['organizer']));
    }

    /**
     * Create Event
     * 
     * Creates a new event. Only organizers and admins can create events.
     * 
     * @authenticated
     */
    #[Response('{"message": "Unauthenticated."}', 401)]
    #[Response('{"message": "This action is unauthorized."}', 403)]
    #[Response('{"message": "A similar event already exists!"}', 409)]
    #[ResponseFromApiResource(EventResource::class, Event::class, 201, with: ['type', 'organizer'], additional: ['message' => 'Event created successfully'])]
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
        $event->public = $request->public ? 1 : 0;
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
     * Show Event
     * 
     * Display a specified event.
     */
    #[UrlParam("id", "int", "The ID of the event to display.", true, 1)]
    
    #[QueryParam("with", "string", "The additional data to include in the response.", false, enum: ["organizer"])]

    #[Response('{"message": "Event not found."}', 404)]
    #[ResponseFromApiResource(EventResource::class, Event::class, 200, with: ['type', 'organizer'], withCount: ['attendees'])]
    public function show(Event $event): EventResource
    {
        $event = $this->loadRelationships($event, ['organizer']);
        $event->loadCount('attendees');
        $event->load('type');

        return EventResource::make($event);
    }

    /**
     * Update Event
     * 
     * Updates the specified event. Only the organizer or an admin can update an event.<br/>
     * You can modify a single field or multiple fields at once.<br/>
     * If some users have already paid for the event, only the name, description and the type can be modified.
     * 
     * @authenticated
     */
    #[UrlParam("id", "int", "The ID of the event to update.", true, 1)]

    #[BodyParam("name", "string", "The name of the event. (Max " . Constants::STRING_MAX_LENGTH . " characters)",  false)]
    #[BodyParam("description", "string", "The description of the event. (Max " . Constants::DESCRIPTION_MAX_LENGTH . " characters)", false)]
    #[BodyParam("start_date", "date", "The start date & time of the event, it needs to be at least " . Constants::MIN_HOURS_BEFORE_START_EVENT . " hours in the future.<br/>", false, example: "2025-01-31 08:00:00")]
    #[BodyParam("end_date", "date", "The end date & time of the event, it needs to be set after the start date.<br/>", false, example: "2025-02-10 20:30:00")]
    #[BodyParam("location", "string", "The location at which the event will take place.", false, "Online")]
    #[BodyParam("cost", "integer", "The amount of tokens each attendees will have to pay to join the event. [0;100]", false)]
    #[BodyParam("public", "boolean", "Is this event a public event, in which case any users will have the ability to join.", false)]
    #[BodyParam("type", "string", "The type of the event, it needs to be one of our existing event types.<br/>", false, example: "Conference")]
    
    #[Response('{"message": "Unauthenticated."}', 401)]
    #[Response('{"message": "This action is unauthorized."}', 403)]
    #[Response('{"message": "The start of this event is too close, modification are not allowed anymore!"}', 403)]
    #[Response('{"message": "Event not found."}', 404)]
    #[Response('{"message": "A similar event already exists!"}', 409)]
    #[Response('{"message": "No changes were made to the event."}', 409)]
    #[ResponseFromApiResource(EventResource::class, Event::class, 200, with: ['type', 'organizer'], withCount: ['attendees'], additional: ['message' => 'Event updated successfully'])]
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
            $event->public = $request->input('public', $event->public) ? 1 : 0;
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
     * Delete Event
     * 
     * Deletes the specified event. If the event has attendees, they will be refunded.<br/>
     * If the event was supposed to start soon, only an admin can delete it.
     * 
     * @authenticated
     */
    #[UrlParam("id", "int", "The ID of the event to delete.", true, 1)]

    #[Response('{"message": "Unauthenticated."}', 401)]
    #[Response('{"message": "This action is unauthorized."}', 403)]
    #[Response('{"message": "The deletion of this event is not allowed anymore!"}', 403)]
    #[Response('{"message": "Event not found."}', 404)]
    #[Response( status: 204)]
    public function destroy(Request $request, Event $event): JsonResponse|\Illuminate\Http\Response
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

    /**
     * Events by Type
     * 
     * Displays a list of events of a specific type.
     */
    #[UrlParam("eventType_name", "string", "The name of the type.", true, "Conference")]

    #[QueryParam("page", "int", "The results are paginated, you will get " . Constants::EVENTS_PER_PAGE . " results per page.", false, 2)]
    #[QueryParam("with", "string", "The additional data to include in the response.", false, enum: ["organizer"])]
    #[QueryParam("sort", "string", "The sorting criteria for the events. Default is 'start,*order*'.<br/>Consisting of two parts, the sorting criteria and the sorting order (asc or desc).", false, "cost,desc", enum: ["start,*order*", "name,*order*", "end,*order*", "cost,*order*", "attendees,*order*", "newest,*order*"])]
    
    #[Response('{"message": "EventType not found."}', 404)]
    #[Response('{"message": "The page 10 does not exist."}', 404)]
    #[ResponseFromApiResource(EventCollection::class, Event::class, 200, paginate: Constants::EVENTS_PER_PAGE, with: ['type'], withCount: ['attendees'])]
    public function type(Request $request, EventType $eventType): EventCollection|JsonResponse
    {
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

    /**
     * Search Events
     * 
     * Searches for events based on various criteria such as name, description, location, cost, date, type, and organizer.
     * You can filter events by a single field or multiple fields at once.
     */
    #[QueryParam("page", "int", "The results are paginated, you will get " . Constants::EVENTS_PER_PAGE . " results per page.", false, 2)]
    #[QueryParam("with", "string", "The additional data to include in the response.", false, enum: ["organizer"])]
    #[QueryParam("sort", "string", "The sorting criteria for the events. Default is 'start,*order*'.<br/>Consisting of two parts, the sorting criteria and the sorting order (asc or desc).", false, "cost,desc", enum: ["start,*order*", "name,*order*", "end,*order*", "cost,*order*", "attendees,*order*", "newest,*order*"])]
    
    #[Response('{"message": "The page 10 does not exist."}', 404)]
    #[ResponseFromApiResource(EventCollection::class, Event::class, 200, paginate: Constants::EVENTS_PER_PAGE, with: ['type'], withCount: ['attendees'])]
    public function search(SearchEventRequest $request): EventCollection|JsonResponse
    {
        [$order, $direction] = cleanSorting($request->input('sort', ''), 'event');

        $events = Event::with('type')
            ->withCount('attendees')
            ->when($request->only(['name', 'description', 'location']), function ($query) use ($request) {
                foreach (['name', 'description', 'location'] as $field) {
                    if ($request->has($field)) {
                        $value = $request->input($field);
                        $operator = "LIKE";

                        if (isset($value[0]) && $value[0] === '-') {
                            $operator = "NOT LIKE";
                            $value = substr($value, 1);
                        }

                        $query->where($field, $operator, '%' . $value . '%');
                    }
                }
            })
            ->when($request->only(['cost_min', 'cost_max']), function ($query) use ($request) {
                if ($request->has('cost_max'))
                    $query->where('cost', '<=', $request->input('cost_max'));
                if ($request->has('cost_min'))
                    $query->where('cost', '>=', $request->input('cost_min'));
            })
            /**
             * We use a subquery to count the number of attendees for each event.
             * We could directly use having but testing uses SQLite which would throw an "General error: 1 HAVING clause on a non-aggregate query"
             * So we use a subquery to count the number of attendees for each event and then filter based on that count.
             * We need to use `CAST` to ensure the count is treated as an integer in SQLite and not a string otherwise the comparison will fail.
             */
            ->when($request->has('attendees_max'), function ($query) use ($request) {
                $query->whereRaw('CAST((select count(*) from users inner join attending on users.id = attending.user_id where events.id = attending.event_id) as UNSIGNED) <= ?', [$request->input('attendees_max')]);
            })
            ->when($request->has('attendees_min'), function ($query) use ($request) {
                $query->whereRaw('CAST((select count(*) from users inner join attending on users.id = attending.user_id where events.id = attending.event_id) as UNSIGNED) >= ?', [$request->input('attendees_min')]);
            })
            ->when($request->only(['starts_before', 'starts_after']), function ($query) use ($request) {
                if ($request->has('starts_before'))
                    $query->where('start_date', '<=', $request->input('starts_before'));
                if ($request->has('starts_after'))
                    $query->where('start_date', '>=', $request->input('starts_after'));
            })
            ->when($request->only(['ends_before', 'ends_after']), function ($query) use ($request) {
                if ($request->has('ends_before'))
                    $query->where('end_date', '<=', $request->input('ends_before'));
                if ($request->has('ends_after'))
                    $query->where('end_date', '>=', $request->input('ends_after'));
            })
            ->when($request->has('type'), function ($query) use ($request) {
                $type = EventType::where('name', $request->input('type'))->first();

                if ($type)
                    $query->where('event_type_id', $type->id);
            })
            ->when($request->has('public'), function ($query) use ($request) {
                $query->where('public', $request->input('public'));
            })
            ->when($request->has('organizer'), function ($query) use ($request) {
                $query->where('user_id', $request->input('organizer'));
            })
            ->orderBy(Constants::EVENT_SORTING_OPTIONS[$order], $direction)
            ->paginate(Constants::EVENTS_PER_PAGE);

        if ($request->has('page') && $request->input('page') > $events->lastPage()) {
            return response()->json([
                'message' => "The page " . $request->input('page') . " does not exist",
            ], 404);
        }

        $events->appends($request->only(['name', 'description', 'location', 'cost_max', 'cost_min', 'starts_before', 'starts_after', 'ends_before', 'ends_after', 'ends_before', 'type', 'attendees_min', 'attendees_max', 'public']));

        // Only add the sort parameter to the URL if it is not the default sorting
        if ($order !== Constants::EVENT_DEFAULT_SORTING || $direction !== 'asc') {
            $events->appends(['sort' => $order . ',' . $direction]);
        }

        return new EventCollection($this->loadRelationships($events, ['organizer']));
    }

    /**
     * Events By Organizer
     * 
     * Lists all events created by a specific organizer.
     */
    #[UrlParam("organizer_id", "int", "The ID of the organizer.", true, 1)]
    
    #[QueryParam("page", "int", "The results are paginated, you will get " . Constants::EVENTS_PER_PAGE . " results per page.", false, 2)]
    #[QueryParam("with", "string", "The additional data to include in the response.", false, enum: ["organizer"])]
    #[QueryParam("sort", "string", "The sorting criteria for the events. Default is 'start,*order*'.<br/>Consisting of two parts, the sorting criteria and the sorting order (asc or desc).", false, "cost,desc", enum: ["start,*order*", "name,*order*", "end,*order*", "cost,*order*", "attendees,*order*", "newest,*order*"])]
    
    #[Response('{"message": "The page 10 does not exist."}', 404)]
    #[ResponseFromApiResource(EventCollection::class, Event::class, 200, paginate: Constants::EVENTS_PER_PAGE, with: ['type'], withCount: ['attendees'])]
    public function byOrganizer(Request $request, User $organizer): EventCollection|JsonResponse
    {
        [$order, $direction] = cleanSorting($request->input('sort', ''), 'event');

        $events = Event::status($request->input('past', false))
            ->with('type')
            ->withCount('attendees')
            ->where('user_id', $organizer->id)
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

        return new EventCollection($events);
    }
}