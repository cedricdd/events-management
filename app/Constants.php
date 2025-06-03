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
        'newest' => 'created_at',
    ];
    const EVENT_DEFAULT_SORTING = 'start';

    const USER_SORTING_OPTIONS = [
        'name' => 'name',
        'country' => 'country',
        'registration' => 'pivot_id',
    ];
    const USER_DEFAULT_SORTING = 'name';

    const MIN_HOURS_BEFORE_START_EVENT = 96;

    const TYPES = [
        'Conference' => 'Professional gatherings for sharing knowledge and networking.',
        'Workshop' => 'Hands-on sessions focused on skill development.',
        'Seminar' => 'Educational meetings for discussion and learning.',
        'Concert' => 'Live music performances by artists or bands.',
        'Festival' => 'Large-scale celebrations featuring entertainment and activities.',
        'Meetup' => 'Informal gatherings for people with shared interests.',
        'Exhibition' => 'Displays of art, products, or innovations.',
        'Networking' => 'Events designed to connect professionals.',
        'Fundraiser' => 'Events aimed at raising money for a cause.',
        'Ceremony' => 'Formal occasions marking special events or achievements.',
        'Competition' => 'Contests where participants compete for prizes.',
        'Panel Discussion' => 'Expert-led discussions on specific topics.',
        'Trade Show' => 'Industry events showcasing products and services.',
        'Product Launch' => 'Unveiling of new products or services.',
        'Hackathon' => 'Collaborative programming and problem-solving events.',
        'Charity Event' => 'Gatherings to support charitable organizations.',
        'Sports Event' => 'Competitions or exhibitions in various sports.',
        'Lecture' => 'Educational talks by experts or academics.',
        'Retreat' => 'Events focused on relaxation, reflection, or team building.',
        'Open House' => 'Events where organizations invite the public to visit and learn more.',
        'Gala' => 'Formal social gatherings often featuring dinner and entertainment.',
        'Bootcamp' => 'Intensive training sessions focused on rapid skill development.',
        'Screening' => 'Showings of films, documentaries, or videos to an audience.',
    ];
}