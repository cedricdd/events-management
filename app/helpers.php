<?php

use App\Constants;

function cleanSorting(string $sorting, string $model): array
{
    switch ($model) {
        case 'user':
            $default = Constants::USER_DEFAULT_SORTING;
            $options = Constants::USER_SORTING_OPTIONS;
            break;
        case 'event':
            $default = Constants::EVENT_DEFAULT_SORTING;
            $options = Constants::EVENT_SORTING_OPTIONS;
            break;
    }

    $sorting = strtolower($sorting);

    if (!empty($sorting)) {
        $infos = explode(',', $sorting);

        if (count($infos) == 1)
            [$order, $direction] = [$infos[0], 'asc'];
        elseif (count($infos) == 2)
            [$order, $direction] = $infos;
        else
            [$order, $direction] = [$default, 'asc'];
    } else
        [$order, $direction] = [$default, 'asc'];

    $order = trim($order);
    $direction = trim($direction);

    if ($direction !== 'asc' && $direction !== 'desc')
        $direction = 'asc'; // Invalid direction, default to asc

    if(!isset($options[$order])) [$order, $direction] = [$default, 'asc'];

    return [$order, $direction];
}