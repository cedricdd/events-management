<?php

namespace App\Models;

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
        $infos = explode(',', $sorting);

        if(count($infos) == 1) [$order, $direction] = [$infos[0], 'asc'];
        elseif(count($infos) == 2) [$order, $direction] = $infos;
        else [$order, $direction] = ['name', 'asc']; // Invalid sort, default to name asc

        if($direction !== 'asc' && $direction !== 'desc') {
            $direction = 'asc'; // Invalid direction, default to asc
        }

        return match(strtolower($order)) {
            'name' => $query->orderBy('name', $direction),
            'start' => $query->orderBy('start_date', $direction),
            'end' => $query->orderBy('end_date', $direction),
            'attendees' => $query->orderBy('attendees_count', $direction),
            'location' => $query->orderBy('location', $direction),
            'cost' => $query->orderBy('cost', $direction),
            default => $query->orderBy('start_date'),
        };
    }
}
