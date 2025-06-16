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

    public function type(): BelongsTo
    {
        return $this->belongsTo(related: EventType::class, foreignKey: 'event_type_id');
    }

    public function invitedUsers(): BelongsToMany
    {
        return $this->belongsToMany(related: User::class, table: 'invites')->withTimestamps();
    }

    public function scopeStatus(Builder $query, bool $pastEvents = false): Builder
    {
        if($pastEvents) return $query->where('start_date', '<', now());
        else return $query->where('end_date', '>=', now());
    }
}
