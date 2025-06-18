<div>
    <p>Hello {{ $user->name }}!</p>

    <p>You are registered to attempt the event '{{ $event->name }}', the organizer has modified the event.</p>

    <p>Here are the details of the event:</p>

    <ul>
        <li><strong>Name</strong>: {{ $event->name }}</li>
        <li><strong>Description</strong>: {{ $event->description }}</li>
        <li><strong>Start</strong>: {{ \Carbon\Carbon::parse($event->start_date)->diffForHumans() }}</li>
        <li><strong>End</strong>: {{ \Carbon\Carbon::parse($event->end_date)->diffForHumans() }}</li>
        <li><strong>Location</strong>: {{ $event->location }}</li>
        <li><strong>Cost</strong>: {{ $event->cost }}</li>
        <li><strong>Type</strong>: {{ $event->type->name }}</li>
        <li><strong>Public</strong>: {{ $event->public ? "yes" : "no" }}</li>
    </ul>

    <p>Thank you for using our application!</p>
</div>