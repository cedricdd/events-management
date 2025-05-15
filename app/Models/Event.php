<?php

namespace App\Models;

use App\Constants;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Event extends Model
{
    use HasFactory;

    public function attendees(): BelongsToMany
    {
        return $this->belongsToMany(related: User::class, table: 'attending');
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(related: User::class, foreignKey: 'user_id');
    }

    public function scopeIsActive(Builder $query): Builder
    {
        return $query->where('end_date', '>=', now());
    }

    public function scopeFinished(Builder $query): Builder
    {
        return $query->where('end_date', '<', now());
    }

    public function scopeSetSorting(Builder $query, string $sorting): Builder
    {
        $order = Constants::EVENT_DEFAULT_SORTING;
        $direction = "ASC";

        $sorting = trim(strtolower($sorting));

        if (!empty($sorting)) {
            $infos = explode(',', $sorting);

            if (count($infos) == 1)
                [$order, $direction] = [$infos[0], 'asc'];
            elseif (count($infos) == 2)
                [$order, $direction] = $infos;
        }

        if ($direction !== 'asc' && $direction !== 'desc') 
            $direction = 'asc'; // Invalid direction, default to asc
        
        $order = Constants::EVENT_SORTING_OPTIONS[$order] ?? Constants::EVENT_DEFAULT_SORTING;

        return $query->orderBy($order, $direction);
    }
}
