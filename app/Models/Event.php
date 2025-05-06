<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Event extends Model
{
    use HasFactory;

    protected $hidden = [
        'updated_at',
        'created_at',
        'user_id',
    ];

    public function attendees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'attending');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
