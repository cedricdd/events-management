<h1>Private Event Invitation</h1>

<div>
    <p>Hello {{ $user->name }},</p>
    <p>You have been invited to attempt the private event <strong>{{ $event->name }}</strong>.</p>
    <p>Event details:</p>
    <ul>
        <li><strong>Name</strong>: {{ $event->name }}</li>
        <li><strong>Description</strong>: {{ $event->description }}</li>
        <li><strong>Start</strong>: {{ \Carbon\Carbon::parse($event->start_date)->diffForHumans() }} (on {{ \Carbon\Carbon::parse($event->start_date)->format('Y-m-d H:i') }})</li>
        <li><strong>End</strong>: {{ \Carbon\Carbon::parse($event->end_date)->diffForHumans() }} (on {{ \Carbon\Carbon::parse($event->end_date)->format('Y-m-d H:i') }})</li>
        <li><strong>Location</strong>: {{ $event->location }}</li>
        <li><strong>Cost</strong>: {{ $event->cost }}</li>
        <li><strong>Type</strong>: {{ $event->type->name }}</li>
    </ul>
    <p>You can join a private event the same way you would join a public event as long as you have been invited by the organizer.</p>
    <p>Thank you for using our application!</p>
</div>