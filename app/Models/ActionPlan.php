<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActionPlan extends Model
{
    protected $guarded = ['id'];

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }
}
