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
        return $this->belongsToMany(related: User::class, table: 'attending')->withPivot('id');
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
}
