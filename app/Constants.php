<?php

namespace App;

class Constants
{
    const STRING_MAX_LENGTH = 255;
    const DESCRIPTION_MAX_LENGTH = 4096;

    const EVENTS_PER_PAGE = 10;
    const ATTENDEES_PER_PAGE = 20;

    const EVENT_SORTING_OPTIONS = [
        'name' => 'name',
        'start' => 'start_date',
        'end' => 'end_date',
        'cost' => 'cost',
        'attendees' => 'attendees_count',
        'location' => 'location',
    ];
    const EVENT_DEFAULT_SORTING = 'start';
}